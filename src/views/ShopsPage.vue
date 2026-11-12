<template>
  <ion-page
    ><ion-header
      ><ion-toolbar
        ><ion-title>{{ isMap ? 'Explore nearby' : 'Find your shop' }}</ion-title></ion-toolbar
      ></ion-header
    ><ion-content
      ><main class="page-shell">
        <span class="eyebrow">DISCOVER SOMETHING SPECIAL</span>
        <h1>{{ isMap ? 'Closer to your next look.' : 'Beautiful finds. Local shops.' }}</h1>
        <p>
          {{
            isMap
              ? 'Use your location to discover nearby formalwear. Distances are approximate and straight-line.'
              : 'Browse approved rental shops and find something for your occasion.'
          }}
        </p>
        <ion-searchbar v-model="query" placeholder="Search shops by name or address" @ion-input="scheduleSearch" />
        <div v-if="isMap" class="actions">
          <ion-button :disabled="locating" @click="locate">{{
            locating ? 'Finding your location...' : 'Find nearest shop'
          }}</ion-button><ion-select v-model="radius" label="Distance" @ion-change="load"
            ><ion-select-option v-for="distance in [1, 3, 5, 10, 20]" :key="distance" :value="distance"
              >Within {{ distance }} km</ion-select-option
            ><ion-select-option :value="0">All shops</ion-select-option></ion-select
          >
        </div>
        <p v-if="locationError" class="notice" role="status">{{ locationError }}</p>
        <ShopMap v-if="isMap" :shops="shops" :location="location" @select="selected = $event" />
        <section v-if="selected" class="panel">
          <h2>{{ selected.shop_name }}</h2>
          <p>{{ selected.address }}</p>
          <p>{{ selected.description }}</p>
          <ion-button :router-link="`/products?shop_id=${selected.id}`">Browse formalwear</ion-button
          ><ion-button @click="openDirections(Number(selected.latitude), Number(selected.longitude))"
            >Get directions</ion-button
          ><ion-button fill="clear" @click="selected = undefined">Close</ion-button>
        </section>
        <div v-if="loading" class="empty" role="status">
          <ion-spinner />
          <p>Finding lovely places...</p>
        </div>
        <div v-else-if="error" class="empty panel" role="alert">
          <ion-icon :icon="cloudOfflineOutline" />
          <h2>We couldn't load the shops</h2>
          <p>{{ error }}</p>
          <ion-button fill="outline" @click="load">Try again</ion-button>
        </div>
        <div v-else-if="!shops.length" class="empty">
          <ion-icon :icon="storefrontOutline" />
          <h2>No shops found yet</h2>
          <p>Try a different search or expand your distance.</p>
        </div>
        <section v-else class="grid" style="margin-top: 24px">
          <article v-for="shop in shops" :key="shop.id" class="panel shop-card">
            <img v-if="shop.logo_url" :src="shop.logo_url" :alt="shop.shop_name" />
            <h3>{{ shop.shop_name }}</h3>
            <p>{{ shop.address }}</p>
            <p v-if="shop.distance_km != null" class="muted">
              {{ Number(shop.distance_km).toFixed(1) }} km / straight-line distance
            </p>
            <ion-button fill="outline" @click="selected = shop">View shop</ion-button>
          </article>
        </section>
      </main></ion-content
    ></ion-page
  >
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonSearchbar,
  IonButton,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonIcon,
} from '@ionic/vue';
import { cloudOfflineOutline, storefrontOutline } from 'ionicons/icons';
import ShopMap from '../components/ShopMap.vue';
import { api } from '../services/api';
import { currentLocation } from '../services/geolocationService';
import { openDirections } from '../services/mapService';
import type { Shop } from '../types';
const route = useRoute();
const isMap = computed(() => route.path === '/map');
const shops = ref<Shop[]>([]),
  query = ref(''),
  error = ref(''),
  loading = ref(false),
  locating = ref(false),
  locationError = ref(''),
  radius = ref(10);
const selected = ref<Shop>();
const location = ref<{ latitude: number; longitude: number }>();
let timer: ReturnType<typeof setTimeout>;
let controller: AbortController | undefined;
async function load() {
  controller?.abort();
  const active = new AbortController();
  controller = active;
  loading.value = true;
  error.value = '';
  const params = new URLSearchParams({ search: query.value });
  if (route.query.category) params.set('category', String(route.query.category));
  if (location.value) {
    params.set('latitude', String(location.value.latitude));
    params.set('longitude', String(location.value.longitude));
    if (radius.value) params.set('radius', String(radius.value));
  }
  try {
    const result = await api<{ data: Shop[] }>(`${location.value ? 'shops/nearby' : 'shops'}?${params}`, {
      signal: active.signal,
    });
    if (!active.signal.aborted) shops.value = result.data;
  } catch (cause) {
    if (!active.signal.aborted) error.value = cause instanceof Error ? cause.message : 'Unable to load shops.';
  } finally {
    if (!active.signal.aborted) loading.value = false;
  }
}
function scheduleSearch() {
  clearTimeout(timer);
  timer = setTimeout(load, 350);
}
async function locate() {
  locating.value = true;
  locationError.value = '';
  try {
    location.value = await currentLocation();
    await load();
  } catch (cause) {
    locationError.value = cause instanceof Error ? cause.message : 'Location unavailable.';
  } finally {
    locating.value = false;
  }
}
watch(
  () => route.fullPath,
  () => {
    if (['/shops', '/map'].includes(route.path)) void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  clearTimeout(timer);
  controller?.abort();
});
</script>
