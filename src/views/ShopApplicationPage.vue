<template>
  <ion-page
    ><ion-header
      ><ion-toolbar
        ><ion-buttons slot="start"><ion-back-button default-href="/profile" /></ion-buttons
        ><ion-title>Join Rentique</ion-title></ion-toolbar
      ></ion-header
    ><ion-content
      ><main class="page-shell">
        <span class="eyebrow">GROW YOUR RENTAL BUSINESS</span>
        <h1>A new home for your shop.</h1>
        <p>
          Tell us about your shop and confirm its physical location. Your shop stays private until administrator
          approval.
        </p>
        <div v-if="!auth.user" class="panel">
          <p>Sign in before submitting a shop application.</p>
          <ion-button router-link="/profile">Sign in</ion-button>
        </div>
        <form v-else class="panel" @submit.prevent="submit">
          <div class="grid">
            <div v-for="field in fields" :key="field.key" class="field">
              <label :for="field.key">{{ field.label }}</label><input
                :id="field.key"
                v-model="form[field.key]"
                :type="field.key === 'email' ? 'email' : 'text'"
                required
                :maxlength="field.key === 'description' ? 5000 : 120"
              />
            </div>
          </div>
          <LocationPicker @confirm="coordinates = $event" @change="coordinates = undefined" />
          <p v-if="coordinates" class="notice">Location confirmed. Ready to submit.</p>
          <p v-if="message" role="status" class="notice">{{ message }}</p>
          <ion-button type="submit" :disabled="!coordinates || busy || submitted">{{
            submitted ? 'Application submitted' : busy ? 'Submitting…' : 'Submit application'
          }}</ion-button>
        </form>
      </main></ion-content
    ></ion-page
  >
</template>
<script setup lang="ts">
import { reactive, ref } from 'vue';
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton, IonButton } from '@ionic/vue';
import LocationPicker from '../components/LocationPicker.vue';
import { useAuthStore } from '../stores/auth';
import { api } from '../services/api';
const auth = useAuthStore();
const fields = [
  { key: 'shop_name', label: 'Shop name' },
  { key: 'email', label: 'Business email' },
  { key: 'contact_number', label: 'Contact number' },
  { key: 'description', label: 'Shop description' },
  { key: 'address', label: 'Street address' },
  { key: 'province', label: 'Province' },
  { key: 'municipality', label: 'City / Municipality' },
  { key: 'barangay', label: 'Barangay' },
] as const;
const form = reactive({
  shop_name: '',
  email: '',
  contact_number: '',
  description: '',
  address: '',
  province: '',
  municipality: '',
  barangay: '',
});
const coordinates = ref<{ latitude: number; longitude: number }>(),
  message = ref(''),
  busy = ref(false),
  submitted = ref(false);
async function submit() {
  if (!coordinates.value || submitted.value) return;
  busy.value = true;
  message.value = '';
  try {
    await api('owner/shop-applications', {
      method: 'POST',
      body: JSON.stringify({ ...form, ...coordinates.value, location_confirmed: true }),
    });
    submitted.value = true;
    message.value = 'Your application has been submitted for review.';
  } catch (cause) {
    message.value = cause instanceof Error ? cause.message : 'Unable to submit application.';
  } finally {
    busy.value = false;
  }
}
</script>
