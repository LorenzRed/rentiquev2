<template>
  <ion-page
    ><ion-header
      ><ion-toolbar
        ><ion-buttons slot="start"><ion-back-button default-href="/profile" /></ion-buttons
        ><ion-title>{{ isAdmin ? 'Platform management' : 'Your rental business' }}</ion-title></ion-toolbar
      ></ion-header
    ><ion-content
      ><main class="page-shell">
        <span class="eyebrow">RENTIQUE WORKSPACE</span>
        <h1>{{ isAdmin ? 'A marketplace worth trusting.' : 'Make every rental count.' }}</h1>
        <div v-if="!auth.user" class="panel">
          <p>Sign in to access your workspace.</p>
          <ion-button router-link="/profile">Sign in</ion-button>
        </div>
        <template v-else
          ><p v-if="message" class="notice" role="status">{{ message }}</p>
          <div class="grid">
            <article v-for="(value, key) in reports" :key="key" class="panel">
              <span class="eyebrow">{{ String(key).replaceAll('_', ' ') }}</span>
              <h2 style="margin: 15px 0 0">{{ String(key).endsWith('centavos') ? money(Number(value)) : value }}</h2>
            </article>
          </div>
          <div class="section-heading">
            <h2>{{ isAdmin ? 'Shop applications & locations' : 'Your shops' }}</h2>
            <ion-button v-if="!isAdmin" router-link="/owner/apply">Register a shop</ion-button
            ><ion-button fill="clear" @click="load">Refresh</ion-button>
          </div>
          <section class="grid">
            <article v-for="shop in shops" :key="shop.id" class="panel">
              <span class="eyebrow">{{ shop.approval_status }}</span>
              <h2>{{ shop.shop_name }}</h2>
              <p>{{ shop.address }}<br />{{ shop.latitude }}, {{ shop.longitude }}</p>
              <ion-button fill="outline" @click="inspected = shop">Inspect location</ion-button>
              <div v-if="isAdmin" class="actions">
                <ion-button
                  v-for="status in ['approved', 'rejected', 'suspended']"
                  :key="status"
                  :disabled="busy"
                  @click="review(shop.id, status)"
                  >{{
                    status === 'approved' ? 'Approve / reactivate' : status === 'rejected' ? 'Reject' : 'Suspend'
                  }}</ion-button
                >
              </div>
              <template v-if="inspected?.id === shop.id"
                ><ShopMap :shops="[shop]" /><ShopFiles :shop-id="shop.id" :readonly="isAdmin" /><LocationPicker
                  v-if="!isAdmin"
                  @confirm="updateLocation(shop.id, $event)"
              /></template>
            </article>
          </section>
          <template v-if="!isAdmin"
            ><div class="section-heading"><h2>Reservations</h2></div>
            <section class="grid">
              <article v-for="booking in reservations" :key="booking.id" class="panel">
                <span class="eyebrow">{{ booking.status.replaceAll('_', ' ') }}</span>
                <h2>Reservation #{{ booking.id }}</h2>
                <p>
                  {{ booking.starts_at }} → {{ booking.ends_at }}<br />Rental {{ money(booking.rental_centavos) }} ·
                  Deposit {{ money(booking.deposit_centavos) }}
                </p>
                <div class="actions">
                  <ion-button
                    v-for="action in actions(booking.status)"
                    :key="action"
                    :disabled="busy"
                    @click="transition(booking.id, action)"
                    >{{ action }}</ion-button
                  >
                </div>
                <form @submit.prevent="payment(booking.id)">
                  <div class="field">
                    <label :for="`amount-${booking.id}`">Cash amount (PHP)</label
                    ><input
                      :id="`amount-${booking.id}`"
                      v-model.number="amounts[booking.id]"
                      type="number"
                      min="0.01"
                      step="0.01"
                      required
                    />
                  </div>
                  <select v-model="kinds[booking.id]" aria-label="Payment type">
                    <option value="rental">Rental</option>
                    <option value="deposit">Deposit</option></select
                  ><ion-button type="submit" fill="outline" :disabled="busy">Record cash payment</ion-button>
                </form>
              </article>
            </section>
            <div class="section-heading"><h2>Add formalwear</h2></div>
            <form class="panel" @submit.prevent="addProduct">
              <div class="grid">
                <div class="field">
                  <label for="product-shop">Shop</label
                  ><select id="product-shop" v-model="product.shop_id" required>
                    <option
                      v-for="shop in shops.filter((s) => s.approval_status === 'approved')"
                      :key="shop.id"
                      :value="shop.id"
                    >
                      {{ shop.shop_name }}
                    </option>
                  </select>
                </div>
                <div class="field">
                  <label for="product-category">Category</label
                  ><select id="product-category" v-model="product.product_category_id" required>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                      {{ category.name }}
                    </option>
                  </select>
                </div>
                <div v-for="field in productFields" :key="field.key" class="field">
                  <label :for="field.key">{{ field.label }}</label><input
                    :id="field.key"
                    v-model="product[field.key]"
                    :type="field.type"
                    required
                  />
                </div>
              </div>
              <ion-button type="submit" :disabled="busy">Add product</ion-button>
            </form></template
          ><template v-else
            ><div class="section-heading"><h2>Customer accounts</h2></div>
            <section class="grid">
              <article v-for="user in users" :key="user.id" class="panel">
                <h3>{{ user.full_name || user.email }}</h3>
                <p>{{ user.email }} · {{ user.account_status }}</p>
                <ion-button v-if="user.role !== 'admin'" fill="outline" :disabled="busy" @click="userStatus(user)">{{
                  user.account_status === 'active' ? 'Suspend' : 'Reactivate'
                }}</ion-button>
              </article>
            </section></template
          ></template
        >
      </main></ion-content
    ></ion-page
  >
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { IonPage, IonHeader, IonToolbar, IonButtons, IonBackButton, IonTitle, IonContent, IonButton } from '@ionic/vue';
import { useAuthStore } from '../stores/auth';
import { api } from '../services/api';
import ShopFiles from '../components/ShopFiles.vue';
import ShopMap from '../components/ShopMap.vue';
import LocationPicker from '../components/LocationPicker.vue';
import type { Shop, AppUser } from '../types';
interface ManagedShop extends Shop {
  approval_status: string;
}
interface Booking {
  id: number;
  status: string;
  starts_at: string;
  ends_at: string;
  rental_centavos: number;
  deposit_centavos: number;
}
const auth = useAuthStore(),
  isAdmin = computed(() => auth.user?.role === 'admin');
const shops = ref<ManagedShop[]>([]),
  users = ref<AppUser[]>([]),
  reservations = ref<Booking[]>([]),
  reports = ref<Record<string, number>>({}),
  categories = ref<{ id: number; name: string }[]>([]),
  message = ref(''),
  busy = ref(false);
const inspected = ref<ManagedShop>();
function updateLocation(id: number, point: { latitude: number; longitude: number }) {
  return run(
    () =>
      api(`owner/shops/${id}/location`, {
        method: 'PATCH',
        body: JSON.stringify({
          ...point,
          formatted_address: shops.value.find((shop) => shop.id === id)?.address,
          location_confirmed: true,
        }),
      }),
    'Shop location updated.',
  );
}
const amounts = reactive<Record<number, number>>({}),
  kinds = reactive<Record<number, string>>({}),
  paymentKeys = new Map<number, string>();
const product = reactive({
  shop_id: undefined as number | undefined,
  product_category_id: undefined as number | undefined,
  name: '',
  description: '',
  size: '',
  color: '',
  daily_price_centavos: 1000,
  deposit_centavos: 0,
  quantity: 1,
});
const productFields = [
  { key: 'name', label: 'Product name', type: 'text' },
  { key: 'description', label: 'Description', type: 'text' },
  { key: 'size', label: 'Size', type: 'text' },
  { key: 'color', label: 'Color', type: 'text' },
  { key: 'daily_price_centavos', label: 'Daily price (PHP)', type: 'number' },
  { key: 'deposit_centavos', label: 'Deposit (PHP)', type: 'number' },
  { key: 'quantity', label: 'Inventory quantity', type: 'number' },
] as const;
function money(value: number) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value / 100);
}
function actions(status: string) {
  return (
    (
      {
        pending: ['approve', 'reject'],
        approved: ['ready', 'pickup'],
        ready_for_pickup: ['pickup'],
        ongoing: ['return'],
        overdue: ['return'],
        returned: ['complete'],
      } as Record<string, string[]>
    )[status] || []
  );
}
async function load() {
  if (!auth.user) return;
  try {
    const [s, r] = await Promise.all([
      api<{ data: ManagedShop[] }>(isAdmin.value ? 'admin/shops' : 'owner/shops'),
      api<Record<string, number>>('owner/reports'),
    ]);
    shops.value = s.data;
    reports.value = r;
    if (isAdmin.value) users.value = (await api<{ data: AppUser[] }>('admin/users')).data;
    else {
      reservations.value = (await api<{ data: Booking[] }>('owner/reservations')).data;
      categories.value = await api('categories');
      for (const b of reservations.value) kinds[b.id] ||= 'rental';
    }
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to load workspace.';
  }
}
async function run(task: () => Promise<unknown>, success: string) {
  busy.value = true;
  message.value = '';
  try {
    await task();
    message.value = success;
    await load();
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to complete action.';
  } finally {
    busy.value = false;
  }
}
function review(id: number, status: string) {
  return run(
    () => api(`admin/shops/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }),
    'Shop status updated.',
  );
}
function userStatus(user: AppUser) {
  return run(
    () =>
      api(`admin/users/${user.id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ account_status: user.account_status === 'active' ? 'suspended' : 'active' }),
      }),
    'Account status updated.',
  );
}
function transition(id: number, action: string) {
  return run(
    () =>
      api(`owner/reservations/${id}/${action}`, {
        method: 'PATCH',
        body: action === 'return' ? JSON.stringify({ late_fee_centavos: 0, damage_fee_centavos: 0 }) : undefined,
      }),
    'Reservation updated.',
  );
}
function payment(id: number) {
  const key = paymentKeys.get(id) || crypto.randomUUID();
  paymentKeys.set(id, key);
  return run(async () => {
    await api(`owner/reservations/${id}/payments`, {
      method: 'POST',
      body: JSON.stringify({ amount_centavos: Math.round(amounts[id] * 100), kind: kinds[id], idempotency_key: key }),
    });
    paymentKeys.delete(id);
  }, 'Cash payment recorded.');
}
function addProduct() {
  return run(
    () =>
      api('owner/products', {
        method: 'POST',
        body: JSON.stringify({
          ...product,
          daily_price_centavos: Math.round(Number(product.daily_price_centavos) * 100),
          deposit_centavos: Math.round(Number(product.deposit_centavos) * 100),
        }),
      }),
    'Product added.',
  );
}
watch(() => auth.user, load, { immediate: true });
</script>
<style scoped>
select {
  width: 100%;
  padding: 14px;
  border: 1px solid #ded3dc;
  border-radius: 9px;
  background: white;
}
.panel h2 {
  margin-top: 12px;
}
.panel form {
  border-top: 1px solid #eee;
  margin-top: 20px;
  padding-top: 20px;
}
</style>
