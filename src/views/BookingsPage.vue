<template>
  <ion-page
    ><ion-header
      ><ion-toolbar><ion-title>Your bookings</ion-title></ion-toolbar></ion-header
    ><ion-content
      ><main class="page-shell">
        <span class="eyebrow">YOUR UPCOMING MOMENTS</span>
        <h1>Something to look forward to.</h1>
        <section class="panel empty">
          <ion-icon :icon="calendarOutline" />
          <h2>{{ auth.user ? 'Your rental history' : 'Keep your occasions in one place' }}</h2>
          <p>
            {{
              auth.user
                ? bookings.length
                  ? 'Your reservations and rental history.'
                  : message
                : 'Sign in to view reservations, pickup details, and your rental history.'
            }}
          </p>
          <ion-button v-if="!auth.user" router-link="/profile">Sign in</ion-button
          ><template v-for="booking in bookings" :key="booking.id"
            ><p>
              Reservation #{{ booking.id }} · {{ booking.status.replaceAll('_', ' ') }}<br />{{ booking.starts_at }} to
              {{ booking.ends_at }}
            </p>
            <ion-button v-if="booking.status === 'pending'" fill="outline" @click="cancel(booking.id)"
              >Cancel reservation</ion-button
            ></template
          >
        </section>
      </main></ion-content
    ></ion-page
  >
</template>
<script setup lang="ts">
import { ref, watch } from 'vue';
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonIcon, IonButton } from '@ionic/vue';
import { calendarOutline } from 'ionicons/icons';
import { useAuthStore } from '../stores/auth';
import { api } from '../services/api';
import type { Reservation } from '../types';
const auth = useAuthStore();
const bookings = ref<Reservation[]>([]);
const message = ref('No reservations yet. Your next occasion starts with a look you love.');
async function cancel(id: number) {
  try {
    await api(`customer/reservations/${id}/cancel`, { method: 'PATCH' });
    const booking = bookings.value.find((item) => item.id === id);
    if (booking) booking.status = 'cancelled';
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to cancel reservation.';
  }
}
watch(
  () => auth.user,
  async (user) => {
    bookings.value = [];
    if (!user) return;
    try {
      bookings.value = (await api<{ data: Reservation[] }>('customer/reservations')).data;
    } catch (cause) {
      message.value = cause instanceof Error ? cause.message : 'Unable to load bookings.';
    }
  },
  { immediate: true },
);
</script>
