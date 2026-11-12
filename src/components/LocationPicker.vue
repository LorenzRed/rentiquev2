<template>
  <div>
    <section>
      <label for="location-search">Search for your shop address</label>
      <div class="actions">
        <input
          id="location-search"
          v-model="search"
          placeholder="Street, city, or landmark"
          minlength="3"
          @keydown.enter.prevent="searchAddress"
        /><ion-button type="button" fill="outline" :disabled="busy || search.length < 3" @click="searchAddress"
          >Search address</ion-button
        >
      </div>
    </section>
    <ion-button
      v-for="result in results"
      :key="result.label"
      fill="clear"
      @click="
        setPoint(result.latitude, result.longitude);
        map?.setView([result.latitude, result.longitude], 16);
      "
      >{{ result.label }}</ion-button
    >
    <p class="muted">Tap the map to select your physical shop location. You can drag the pin to adjust it.</p>
    <div ref="container" class="map-frame" aria-label="Select your shop location" />
    <div class="actions">
      <ion-button fill="outline" :disabled="busy" @click="locate">Use my current location</ion-button>
    </div>
    <p v-if="error" role="alert">{{ error }}</p>
    <div v-if="point" class="notice">
      <strong>Selected shop location</strong>
      <p>Latitude: {{ point.latitude.toFixed(7) }} · Longitude: {{ point.longitude.toFixed(7) }}</p>
      <ion-button @click="emit('confirm', point)">Confirm location</ion-button>
    </div>
  </div>
</template>
<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { IonButton } from '@ionic/vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { currentLocation } from '../services/geolocationService';
import { api } from '../services/api';
const search = ref(''),
  results = ref<{ latitude: number; longitude: number; label: string }[]>([]);
async function searchAddress() {
  busy.value = true;
  error.value = '';
  try {
    results.value = (
      await api<{ data: { latitude: number; longitude: number; label: string }[] }>(
        `geocode?q=${encodeURIComponent(search.value)}`,
      )
    ).data;
    if (!results.value.length) error.value = 'No addresses found. You can select your shop on the map.';
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Address search unavailable.';
  } finally {
    busy.value = false;
  }
}
const emit = defineEmits<{ confirm: [point: { latitude: number; longitude: number }]; change: [] }>();
const container = ref<HTMLElement>(),
  point = ref<{ latitude: number; longitude: number }>(),
  error = ref(''),
  busy = ref(false);
let map: L.Map | undefined, marker: L.Marker | undefined, observer: ResizeObserver | undefined;
function setPoint(latitude: number, longitude: number) {
  point.value = { latitude, longitude };
  emit('change');
  if (!map) return;
  if (marker) marker.setLatLng([latitude, longitude]);
  else {
    marker = L.marker([latitude, longitude], {
      draggable: true,
      icon: L.divIcon({
        className: 'location-pin',
        html: '<span style="display:block;width:24px;height:24px;background:#512b46;border:3px solid white;border-radius:50%;box-shadow:0 2px 8px #0005"></span>',
        iconSize: [24, 24],
        iconAnchor: [12, 12],
      }),
    }).addTo(map);
    marker.on('dragend', () => {
      const position = marker!.getLatLng();
      setPoint(position.lat, position.lng);
    });
  }
}
async function locate() {
  busy.value = true;
  error.value = '';
  try {
    const p = await currentLocation();
    setPoint(p.latitude, p.longitude);
    map?.setView([p.latitude, p.longitude], 16);
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Location unavailable.';
  } finally {
    busy.value = false;
  }
}
onMounted(() => {
  if (!container.value) return;
  map = L.map(container.value).setView([12.5, 122], 5);
  L.tileLayer(import.meta.env.VITE_MAP_TILE_URL || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);
  map.on('click', (event: L.LeafletMouseEvent) => setPoint(event.latlng.lat, event.latlng.lng));
  observer = new ResizeObserver(() => map?.invalidateSize());
  observer.observe(container.value);
});
onBeforeUnmount(() => {
  observer?.disconnect();
  map?.remove();
});
</script>
