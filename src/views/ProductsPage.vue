<template>
  <ion-page
    ><ion-header
      ><ion-toolbar
        ><ion-buttons slot="start"><ion-back-button default-href="/shops" /></ion-buttons
        ><ion-title>The formalwear edit</ion-title></ion-toolbar
      ></ion-header
    ><ion-content
      ><main class="page-shell">
        <span class="eyebrow">FIND YOUR MOMENT</span>
        <h1>A look you'll remember.</h1>
        <ion-searchbar v-model="search" placeholder="Search formalwear" @ion-change="load" />
        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="loading">Loading formalwear…</p>
        <div v-else-if="!products.length" class="panel empty">
          <h2>No formalwear found</h2>
          <p>Try a different search or check back as shops add their collections.</p>
        </div>
        <section class="grid" style="margin-top: 24px">
          <article v-for="product in products" :key="product.id" class="panel">
            <img
              v-if="product.image_url"
              class="product-image"
              :src="product.image_url"
              :alt="product.name"
              loading="lazy"
            />
            <span class="eyebrow">{{ product.category }}</span>
            <h2>{{ product.name }}</h2>
            <p>{{ product.description }}</p>
            <p>{{ product.shop_name }}</p>
            <p>
              <strong>{{ money(product.daily_price_centavos) }}</strong> / calendar day<br /><span class="muted"
                >Refundable deposit: {{ money(product.deposit_centavos) }}</span
              >
            </p>
            <ion-button @click="choose(product)">Check availability</ion-button>
          </article>
        </section>
        <section v-if="selected" class="panel booking-panel">
          <h2>Reserve {{ selected.name }}</h2>
          <p>Both pickup and return dates are charged.</p>
          <div class="grid">
            <div class="field">
              <label for="start-date">Pickup date</label
              ><input
                id="start-date"
                v-model="start"
                type="date"
                :min="today"
                @change="
                  availability = [];
                  quoteMessage = '';
                "
              />
            </div>
            <div class="field">
              <label for="end-date">Return date</label
              ><input
                id="end-date"
                v-model="end"
                type="date"
                :min="start || today"
                @change="
                  availability = [];
                  quoteMessage = '';
                "
              />
            </div>
          </div>
          <ion-button fill="outline" :disabled="!start || !end || busy" @click="check">Check dates</ion-button
          ><template v-if="availability.length"
            ><div class="field">
              <label for="variant">Size and color</label
              ><select id="variant" v-model="variant">
                <option
                  v-for="item in availability"
                  :key="item.id"
                  :value="item.id"
                  :disabled="!item.available_quantity"
                >
                  {{ item.size }} · {{ item.color }} · {{ item.available_quantity }} available
                </option>
              </select>
            </div>
            <p>
              {{ days }} calendar day{{ days === 1 ? '' : 's' }} · Rental
              {{ money(selected.daily_price_centavos * days) }} + deposit {{ money(selected.deposit_centavos) }}
            </p>
            <ion-button :disabled="busy || !variant || !auth.user" @click="reserve">Submit reservation</ion-button
            ><ion-button v-if="!auth.user" router-link="/profile">Sign in to reserve</ion-button></template
          >
          <p v-if="quoteMessage" role="status" class="notice">{{ quoteMessage }}</p>
        </section>
      </main></ion-content
    ></ion-page
  >
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonButtons,
  IonBackButton,
  IonTitle,
  IonContent,
  IonSearchbar,
  IonButton,
} from '@ionic/vue';
import { api } from '../services/api';
import { useAuthStore } from '../stores/auth';
interface Product {
  image_url?: string | null;
  id: number;
  name: string;
  description: string;
  category: string;
  shop_name: string;
  daily_price_centavos: number;
  deposit_centavos: number;
}
interface Variant {
  id: number;
  size: string;
  color: string;
  available_quantity: number;
}
const auth = useAuthStore(),
  route = useRoute();
const products = ref<Product[]>([]),
  selected = ref<Product>(),
  search = ref(''),
  error = ref(''),
  loading = ref(false),
  start = ref(''),
  end = ref(''),
  availability = ref<Variant[]>([]),
  variant = ref<number>(),
  busy = ref(false),
  quoteMessage = ref('');
const today = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(
  new Date(),
);
const days = computed(() =>
  start.value && end.value
    ? Math.max(0, Math.round((Date.parse(end.value) - Date.parse(start.value)) / 86400000) + 1)
    : 0,
);
function money(value: number) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value / 100);
}
async function load() {
  loading.value = true;
  error.value = '';
  const params = new URLSearchParams({ search: search.value });
  for (const key of ['category', 'shop_id']) if (route.query[key]) params.set(key, String(route.query[key]));
  try {
    products.value = (await api<{ data: Product[] }>(`products?${params}`)).data;
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Unable to load products.';
  } finally {
    loading.value = false;
  }
}
function choose(product: Product) {
  selected.value = product;
  availability.value = [];
  variant.value = undefined;
  quoteMessage.value = '';
}
async function check() {
  if (!selected.value) return;
  busy.value = true;
  quoteMessage.value = '';
  try {
    availability.value = (
      await api<{ data: Variant[] }>(
        `products/${selected.value.id}/availability?starts_at=${start.value}&ends_at=${end.value}`,
      )
    ).data;
    variant.value = availability.value.find((item) => item.available_quantity > 0)?.id;
    if (!variant.value) quoteMessage.value = 'No inventory is available for those dates.';
  } catch (cause) {
    quoteMessage.value = cause instanceof Error ? cause.message : 'Unable to check availability.';
  } finally {
    busy.value = false;
  }
}
async function reserve() {
  busy.value = true;
  try {
    const result = await api<{ id: number }>('customer/reservations', {
      method: 'POST',
      body: JSON.stringify({ product_variant_id: variant.value, starts_at: start.value, ends_at: end.value }),
    });
    quoteMessage.value = `Reservation #${result.id} submitted. Your shop will review it shortly.`;
    availability.value = [];
    variant.value = undefined;
  } catch (cause) {
    quoteMessage.value = cause instanceof Error ? cause.message : 'Unable to reserve.';
  } finally {
    busy.value = false;
  }
}
watch(
  () => route.fullPath,
  () => {
    if (route.path === '/products') void load();
  },
  { immediate: true },
);
</script>
<style scoped>
.product-image {
  width: 100%;
  height: 240px;
  object-fit: cover;
  border-radius: 12px;
  margin-bottom: 16px;
}
.booking-panel {
  margin-top: 24px;
}
select {
  width: 100%;
  padding: 14px;
  border: 1px solid #ded3dc;
  border-radius: 9px;
  background: white;
}
</style>
