<template>
  <ion-page
    ><ion-tabs
      ><ion-router-outlet /><ion-tab-bar slot="bottom"
        ><ion-tab-button v-for="tab in tabs" :key="tab.path" :tab="tab.path" :href="`/${tab.path}`"
          ><ion-icon :icon="tab.icon" /><ion-label>{{ tab.label }}</ion-label></ion-tab-button
        ></ion-tab-bar
      ></ion-tabs
    ></ion-page
  >
</template>
<script setup lang="ts">
import { IonPage, IonTabs, IonRouterOutlet, IonTabBar, IonTabButton, IonIcon, IonLabel } from '@ionic/vue';
import { homeOutline, storefrontOutline, mapOutline, calendarOutline, personOutline } from 'ionicons/icons';
const tabs = [
  { path: 'home', label: 'Home', icon: homeOutline },
  { path: 'shops', label: 'Shops', icon: storefrontOutline },
  { path: 'map', label: 'Explore', icon: mapOutline },
  { path: 'bookings', label: 'Bookings', icon: calendarOutline },
  { path: 'profile', label: 'Profile', icon: personOutline },
];
</script>
