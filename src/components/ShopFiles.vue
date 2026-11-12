<template>
  <section class="shop-files">
    <h3>{{ readonly ? 'Verification documents' : 'Shop files' }}</h3>
    <p v-if="message" role="status" class="muted">{{ message }}</p>
    <div v-for="document in documents" :key="document.id">
      <ion-button fill="clear" size="small" @click="open(document.id)">View document #{{ document.id }}</ion-button>
    </div>
    <form v-if="!readonly" @submit.prevent="upload">
      <label :for="`kind-${shopId}`">File purpose</label
      ><select :id="`kind-${shopId}`" v-model="kind" @change="loadProducts">
        <option value="verification">Business verification</option>
        <option value="logo">Shop logo</option>
        <option value="cover">Shop cover image</option>
        <option value="product">Product photo</option>
      </select>
      <template v-if="kind === 'product'"
        ><label :for="`product-${shopId}`">Product</label
        ><select :id="`product-${shopId}`" v-model="productId" required>
          <option :value="undefined" disabled>Choose a product</option>
          <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
        </select></template
      >
      <label :for="`file-${shopId}`">Choose a file (maximum 5 MB)</label
      ><input
        :id="`file-${shopId}`"
        ref="fileInput"
        type="file"
        :accept="
          kind === 'verification'
            ? 'image/jpeg,image/png,image/webp,application/pdf'
            : 'image/jpeg,image/png,image/webp'
        "
        required
        @change="file = ($event.target as HTMLInputElement).files?.[0]"
      /><ion-button type="submit" size="small" :disabled="busy || !file">{{
        busy ? 'Uploading…' : 'Upload file'
      }}</ion-button>
    </form>
  </section>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { IonButton } from '@ionic/vue';
import { api } from '../services/api';
const props = defineProps<{ shopId: number; readonly?: boolean }>();
const fileInput = ref<HTMLInputElement>();
const products = ref<{ id: number; name: string }[]>([]),
  productId = ref<number>();
async function loadProducts() {
  if (kind.value !== 'product') return;
  try {
    products.value = (
      await api<{ data: { id: number; name: string }[] }>(`owner/products?shop_id=${props.shopId}`)
    ).data;
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to load products.';
  }
}
const documents = ref<{ id: number }[]>([]),
  file = ref<File>(),
  kind = ref('verification'),
  message = ref(''),
  busy = ref(false);
async function load() {
  try {
    documents.value = (await api<{ data: { id: number }[] }>(`shops/${props.shopId}/documents`)).data;
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to load documents.';
  }
}
async function open(id: number) {
  try {
    const result = await api<{ url: string }>(`documents/${id}`);
    const url = new URL(result.url);
    const apiOrigin = new URL(import.meta.env.VITE_API_URL).origin;
    if (url.origin !== apiOrigin || !['http:', 'https:'].includes(url.protocol))
      throw new Error('Invalid document link.');
    window.open(url.href, '_blank', 'noopener,noreferrer');
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to open document.';
  }
}
async function upload() {
  if (!file.value) return;
  if (kind.value === 'product' && !productId.value) {
    message.value = 'Choose a product first.';
    return;
  }
  busy.value = true;
  message.value = '';
  const body = new FormData();
  body.append('file', file.value);
  body.append('kind', kind.value);
  if (kind.value === 'product') body.append('product_id', String(productId.value));
  try {
    await api(`owner/shops/${props.shopId}/files`, { method: 'POST', body });
    message.value = 'File uploaded securely.';
    file.value = undefined;
    if (fileInput.value) fileInput.value.value = '';
    await load();
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to upload file.';
  } finally {
    busy.value = false;
  }
}
onMounted(load);
</script>
<style scoped>
.shop-files {
  border-top: 1px solid #eee;
  margin-top: 20px;
  padding-top: 16px;
}
.shop-files h3 {
  font-size: 16px;
}
label {
  display: block;
  font-size: 12px;
  margin: 12px 0 8px;
}
select,
input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ded3dc;
  border-radius: 8px;
  background: white;
}
</style>
