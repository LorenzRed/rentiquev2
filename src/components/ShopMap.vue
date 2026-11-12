<template><div ref="container" class="map-frame" aria-label="Rental shop map" /></template>
<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type { Shop } from '../types';
const props = defineProps<{ shops: Shop[]; location?: { latitude: number; longitude: number } }>();
const emit = defineEmits<{ select: [shop: Shop] }>();
const container = ref<HTMLElement>();
let map: L.Map | undefined;
let observer: ResizeObserver | undefined;
const markers = L.layerGroup();
function render() {
  if (!map) return;
  markers.clearLayers();
  for (const shop of props.shops) {
    const lat = Number(shop.latitude),
      lng = Number(shop.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
    const label = document.createElement('button');
    label.textContent = shop.shop_name;
    label.onclick = () => emit('select', shop);
    L.circleMarker([lat, lng], { radius: 9, color: '#512b46', fillColor: '#c6a76a', fillOpacity: 1 })
      .bindPopup(label)
      .addTo(markers);
  }
  if (!props.location && props.shops.length) {
    const points = props.shops
      .map((shop) => L.latLng(Number(shop.latitude), Number(shop.longitude)))
      .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
    if (points.length) map.fitBounds(L.latLngBounds(points), { padding: [30, 30], maxZoom: 15 });
  }
  if (props.location) {
    L.circleMarker([props.location.latitude, props.location.longitude], {
      radius: 8,
      color: '#fff',
      fillColor: '#397cc4',
      fillOpacity: 1,
    })
      .bindTooltip('Your location')
      .addTo(markers);
    map.setView([props.location.latitude, props.location.longitude], 13);
  }
}
onMounted(() => {
  if (!container.value) return;
  map = L.map(container.value).setView([12.5, 122], 5);
  L.tileLayer(import.meta.env.VITE_MAP_TILE_URL || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);
  markers.addTo(map);
  render();
  observer = new ResizeObserver(() => map?.invalidateSize());
  observer.observe(container.value);
});
watch(() => [props.shops, props.location], render, { deep: true });
onBeforeUnmount(() => {
  observer?.disconnect();
  map?.remove();
});
</script>
