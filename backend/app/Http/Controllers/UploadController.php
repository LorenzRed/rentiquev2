<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    public function store(Request $request, Shop $shop): JsonResponse
    {
        Gate::authorize('update', $shop);
        $data = $request->validate(['kind' => 'required|in:verification,logo,cover,product', 'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,webp,pdf', 'product_id' => 'required_if:kind,product|integer']);
        $file = $request->file('file');
        abort_if($data['kind'] !== 'verification' && $file->getMimeType() === 'application/pdf', 422, 'Images must be JPEG, PNG, or WebP.');
        if ($data['kind'] === 'product') {
            abort_unless(DB::table('products')->where('id', $data['product_id'])->where('shop_id', $shop->id)->exists(), 403);
        }
        $folder = match ($data['kind']) {
            'verification' => 'rentique/verification/'.$shop->id,'product' => 'rentique/shops/'.$shop->id.'/products/'.$data['product_id'],default => 'rentique/shops/'.$shop->id.'/'.$data['kind']
        };
        $path = $file->storeAs($folder, Str::uuid().'.'.$file->extension(), 'local');
        abort_unless($path, 500, 'The file could not be saved. Please try again.');
        $url = null;
        try {
            if ($data['kind'] === 'verification') {
                DB::table('shop_verification_documents')->insert(['shop_id' => $shop->id, 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($data['kind'] === 'product') {
                $imageId = DB::table('product_images')->insertGetId(['product_id' => $data['product_id'], 'path' => $path, 'created_at' => now(), 'updated_at' => now()]);
                $url = route('product-images.show', ['id' => $imageId]);
            } else {
                $shop->{$data['kind'].'_path'} = $path;
                $shop->save();
                $url = route('shops.image', ['shop' => $shop->id, 'kind' => $data['kind']]);
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['path' => $path, 'url' => $url], 201);
    }

    public function documents(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' || $shop->owner_id === $request->user()->id, 403);

        return response()->json(['data' => DB::table('shop_verification_documents')->where('shop_id', $shop->id)->get(['id', 'mime_type', 'size', 'created_at'])]);
    }

    public function document(Request $request, int $id): JsonResponse
    {
        $document = DB::table('shop_verification_documents')->find($id);
        abort_unless($document, 404);
        $shop = Shop::findOrFail($document->shop_id);
        abort_unless($request->user()->role === 'admin' || $shop->owner_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return response()->json(['url' => URL::temporarySignedRoute('documents.download', now()->addMinutes(5), ['id' => $id])]);
    }

    public function downloadDocument(int $id): StreamedResponse
    {
        $document = DB::table('shop_verification_documents')->find($id);
        abort_unless($document, 404);

        return $this->fileResponse($document->path, 'rentique/verification/'.$document->shop_id.'/');
    }

    public function shopImage(Shop $shop, string $kind): StreamedResponse
    {
        abort_unless(Shop::publiclyVisible()->whereKey($shop->id)->exists(), 404);
        abort_unless(in_array($kind, ['logo', 'cover'], true), 404);
        $path = $shop->{$kind.'_path'};
        abort_unless($path, 404);

        return $this->fileResponse($path, 'rentique/shops/'.$shop->id.'/'.$kind.'/');
    }

    public function productImage(int $id): StreamedResponse
    {
        $image = DB::table('product_images')->join('products', 'products.id', '=', 'product_images.product_id')->where('product_images.id', $id)->where('products.is_archived', false)->select('product_images.path', 'products.shop_id', 'products.id as product_id')->first();
        abort_unless($image && Shop::publiclyVisible()->whereKey($image->shop_id)->exists(), 404);

        return $this->fileResponse($image->path, 'rentique/shops/'.$image->shop_id.'/products/'.$image->product_id.'/');
    }

    private function fileResponse(string $path, string $prefix): StreamedResponse
    {
        abort_unless(str_starts_with($path, $prefix) && ! str_contains($path, '..') && ! str_contains($path, "\0"), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
