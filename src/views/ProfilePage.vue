<template>
  <ion-page>
    <ion-header
      ><ion-toolbar
        ><ion-title>{{ auth.user ? 'My profile' : 'Your account' }}</ion-title></ion-toolbar
      ></ion-header
    >
    <ion-content>
      <main class="page-shell account">
        <div v-if="!auth.ready || auth.processing" class="panel empty" role="status">
          <ion-spinner />
          <h2>{{ register ? 'Creating your account' : 'Loading your profile' }}</h2>
          <p>Just a moment...</p>
        </div>
        <template v-else>
          <p
            v-if="!needsCompletion && auth.hasIdentity && (message || auth.error || auth.notice)"
            class="notice"
            role="status"
          >
            {{ message || auth.error || auth.notice }}
          </p>
          <template v-if="auth.user && !needsCompletion">
            <section class="profile-hero">
              <div class="profile-cover">
                <span class="eyebrow">YOUR RENTIQUE ACCOUNT</span><ion-icon :icon="sparklesOutline" />
              </div>
              <div class="profile-summary">
                <div class="avatar">
                  <img
                    v-if="auth.user.profile_image_url && !photoFailed"
                    :src="auth.user.profile_image_url"
                    alt="Your profile photo"
                    @error="photoFailed = true"
                  /><span v-else>{{ initials }}</span>
                </div>
                <div class="summary-copy">
                  <span class="role-badge">{{ roleLabel }}</span>
                  <h1>{{ auth.user.full_name || 'Your profile' }}</h1>
                  <p>{{ auth.user.email }}</p>
                  <span class="muted">{{ memberSince }}</span>
                </div>
                <ion-button fill="outline" size="small" :disabled="busy" @click="photoInput?.click()"
                  ><ion-icon slot="start" :icon="cameraOutline" />Change photo</ion-button
                >
                <input
                  ref="photoInput"
                  class="visually-hidden"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  aria-label="Choose profile photo"
                  @change="uploadPhoto"
                />
              </div>
            </section>
            <div class="profile-grid">
              <section class="panel personal-details">
                <div class="card-heading">
                  <h2>Personal details</h2>
                  <ion-button v-if="!editing" fill="clear" size="small" @click="startEditing"
                    ><ion-icon slot="start" :icon="createOutline" />Edit profile</ion-button
                  >
                </div>
                <form v-if="editing" @submit.prevent="saveDetails">
                  <div class="field">
                    <label for="profile-name">Full name</label
                    ><input id="profile-name" v-model="draft.full_name" autocomplete="name" maxlength="120" required />
                  </div>
                  <div class="field">
                    <label for="profile-phone">Phone number</label
                    ><input
                      id="profile-phone"
                      v-model="draft.phone"
                      type="tel"
                      autocomplete="tel"
                      maxlength="40"
                      placeholder="Add a contact number"
                    />
                  </div>
                  <div class="actions">
                    <ion-button type="submit" :disabled="busy">{{
                      busy ? 'Saving...' : 'Save changes'
                    }}</ion-button><ion-button fill="clear" :disabled="busy" @click="editing = false"
                      >Cancel</ion-button
                    >
                  </div>
                </form>
                <dl v-else class="detail-list">
                  <div>
                    <dt>Full name</dt>
                    <dd>{{ auth.user.full_name || 'Add your name' }}</dd>
                  </div>
                  <div>
                    <dt>Email address</dt>
                    <dd>{{ auth.user.email }}</dd>
                  </div>
                  <div>
                    <dt>Phone number</dt>
                    <dd>{{ auth.user.phone || 'Not added yet' }}</dd>
                  </div>
                  <div>
                    <dt>Account type</dt>
                    <dd>{{ roleLabel }}</dd>
                  </div>
                  <div>
                    <dt>Account status</dt>
                    <dd>
                      <span class="status-pill">{{
                        auth.user.account_status === 'active' ? 'Active' : auth.user.account_status
                      }}</span>
                    </dd>
                  </div>
                </dl>
              </section>
              <div class="profile-side">
                <section class="panel">
                  <div class="card-heading">
                    <h2>Email & security</h2>
                    <ion-icon :icon="shieldCheckmarkOutline" />
                  </div>
                  <p class="verification" :class="{ verified: auth.user.email_verified_at }">
                    <ion-icon :icon="auth.user.email_verified_at ? checkmarkCircleOutline : mailOutline" />{{
                      auth.user.email_verified_at ? 'Email verified' : 'Email verification pending'
                    }}
                  </p>
                  <p class="muted">Your sign-in email is {{ auth.user.email }}.</p>
                  <div v-if="!auth.user.email_verified_at" class="actions">
                    <ion-button fill="outline" size="small" :disabled="busy" @click="verification"
                      >Resend verification</ion-button
                    ><ion-button fill="clear" size="small" :disabled="busy" @click="checkVerification"
                      >I've verified my email</ion-button
                    >
                  </div>
                  <ion-button fill="clear" size="small" :disabled="busy" @click="reset"
                    >Send password reset email</ion-button
                  >
                </section>
                <section class="panel">
                  <h2>
                    {{
                      auth.user.role === 'admin'
                        ? 'Manage Rentique'
                        : auth.user.role === 'owner'
                          ? 'Your rental business'
                          : 'Your next occasion'
                    }}
                  </h2>
                  <p>
                    {{
                      auth.user.role === 'owner'
                        ? 'Set up your shop, follow your application, and manage your rentals. Shops appear publicly after approval.'
                        : auth.user.role === 'admin'
                          ? 'Review shops and manage platform activity.'
                          : 'Find your next look and keep track of every reservation.'
                    }}
                  </p>
                  <ion-button v-if="auth.user.role !== 'customer'" expand="block" router-link="/workspace">{{
                    auth.user.role === 'admin' ? 'Open administrator dashboard' : 'Open shop workspace'
                  }}</ion-button><ion-button v-else expand="block" router-link="/bookings">View my bookings</ion-button
                  ><ion-button
                    v-if="auth.user.role === 'owner'"
                    expand="block"
                    fill="outline"
                    router-link="/owner/apply"
                    >Register your shop</ion-button
                  ><ion-button v-if="auth.user.role === 'customer'" fill="clear" router-link="/shops"
                    >Explore rental shops</ion-button
                  >
                </section>
              </div>
            </div>
            <div class="signout-row">
              <span class="muted">Your looks. Your occasions. Your Rentique.</span
              ><ion-button fill="clear" color="primary" :disabled="busy" @click="logout"
                ><ion-icon slot="start" :icon="logOutOutline" />Sign out</ion-button
              >
            </div>
          </template>
          <template v-else-if="needsCompletion || !auth.hasIdentity">
            <header class="account-intro">
              <span class="eyebrow">WELCOME TO RENTIQUE</span>
              <h1>
                {{
                  needsCompletion
                    ? 'Finish making it yours.'
                    : register
                      ? 'Your next chapter of style.'
                      : 'Lovely to see you.'
                }}
              </h1>
              <p>
                {{
                  needsCompletion
                    ? 'Complete your registration to get started.'
                    : 'Beautiful looks. Memorable moments. All in one place.'
                }}
              </p>
            </header>
            <form class="panel auth-form" @submit.prevent="submit">
              <h2>
                {{
                  needsCompletion
                    ? 'Complete registration'
                    : register
                      ? 'Create your account'
                      : 'Sign in to your account'
                }}
              </h2>
              <template v-if="register || needsCompletion">
                <div class="field">
                  <label for="full-name">Full name</label
                  ><input
                    id="full-name"
                    v-model="fullName"
                    autocomplete="name"
                    maxlength="120"
                    required
                    placeholder="Your full name"
                  />
                </div>
                <fieldset class="account-types">
                  <legend>I want to join as a</legend>
                  <label class="type-option" :class="{ selected: accountType === 'customer' }"
                    ><input v-model="accountType" type="radio" name="account-type" value="customer" /><ion-icon
                      :icon="personOutline"
                    /><strong>Renter / Customer</strong><span>Discover formalwear and book a look.</span></label
                  ><label class="type-option" :class="{ selected: accountType === 'owner' }"
                    ><input v-model="accountType" type="radio" name="account-type" value="owner" /><ion-icon
                      :icon="storefrontOutline"
                    /><strong>Shop Owner</strong><span>Register a shop and rent out your collection.</span></label
                  >
                </fieldset>
                <p v-if="accountType === 'owner'" class="muted">
                  You can set up your shop after registration. Administrator approval is required before your shop goes
                  public.
                </p>
              </template>
              <template v-if="!needsCompletion"
                ><div class="field">
                  <label for="email">Email address</label
                  ><input
                    id="email"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    required
                    placeholder="you@example.com"
                  />
                </div>
                <div class="field">
                  <label for="password">Password</label
                  ><input
                    id="password"
                    v-model="password"
                    type="password"
                    :autocomplete="register ? 'new-password' : 'current-password'"
                    :minlength="register ? 8 : undefined"
                    required
                    :placeholder="register ? 'At least 8 characters' : 'Your password'"
                  />
                </div>
                <div v-if="register" class="field">
                  <label for="confirm-password">Confirm password</label
                  ><input
                    id="confirm-password"
                    v-model="confirmPassword"
                    type="password"
                    autocomplete="new-password"
                    required
                    placeholder="Enter your password again"
                  /></div
              ></template>
              <p v-if="message || auth.error || auth.notice" class="notice" role="status">
                {{ message || auth.error || auth.notice }}
              </p>
              <ion-button type="submit" expand="block" :disabled="busy">{{
                busy
                  ? 'Please wait...'
                  : needsCompletion
                    ? 'Complete registration'
                    : register
                      ? 'Create account'
                      : 'Sign in'
              }}</ion-button>
              <div v-if="!needsCompletion" class="auth-actions">
                <ion-button
                  fill="clear"
                  size="small"
                  @click="
                    register = !register;
                    message = '';
                    confirmPassword = '';
                  "
                  >{{
                    register ? 'Already have an account? Sign in' : 'New here? Create an account'
                  }}</ion-button><ion-button v-if="!register" fill="clear" size="small" @click="reset"
                  >Forgot password?</ion-button
                >
              </div>
              <ion-button v-else fill="clear" :disabled="busy" @click="logout">Sign out</ion-button>
            </form>
          </template>
          <section v-else class="panel empty">
            <h2>Let's reconnect your account</h2>
            <p>Your sign-in succeeded, but your profile could not be loaded.</p>
            <ion-button :disabled="busy" @click="retry">Try again</ion-button
            ><ion-button fill="clear" @click="logout">Sign out</ion-button>
          </section>
        </template>
      </main>
    </ion-content>
  </ion-page>
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonButton,
  IonIcon,
  IonSpinner,
  onIonViewWillEnter,
} from '@ionic/vue';
import {
  cameraOutline,
  createOutline,
  shieldCheckmarkOutline,
  checkmarkCircleOutline,
  mailOutline,
  personOutline,
  storefrontOutline,
  logOutOutline,
  sparklesOutline,
} from 'ionicons/icons';
import { useAuthStore } from '../stores/auth';
const auth = useAuthStore();
const email = ref(''),
  password = ref(''),
  confirmPassword = ref(''),
  fullName = ref(''),
  message = ref(''),
  register = ref(false),
  busy = ref(false),
  editing = ref(false),
  photoFailed = ref(false),
  accountType = ref<'customer' | 'owner'>('customer');
const photoInput = ref<HTMLInputElement>();
const draft = reactive({ full_name: '', phone: '' });
const needsCompletion = computed(() =>
  Boolean(auth.user && auth.user.role !== 'admin' && !auth.user.registration_completed_at),
);
const roleLabel = computed(() =>
  auth.user?.role === 'owner' ? 'Shop Owner' : auth.user?.role === 'admin' ? 'Administrator' : 'Renter / Customer',
);
const initials = computed(() =>
  (auth.user?.full_name || auth.user?.email || 'R')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase(),
);
const memberSince = computed(() =>
  auth.user?.created_at
    ? `Member since ${new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric' }).format(new Date(auth.user.created_at))}`
    : 'Rentique member',
);
function errorMessage(cause: unknown) {
  const code = typeof cause === 'object' && cause && 'code' in cause ? String(cause.code) : '';
  const messages: Record<string, string> = {
    'auth/invalid-credential': 'The email or password is incorrect.',
    'auth/wrong-password': 'The email or password is incorrect.',
    'auth/invalid-email': 'Enter a valid email address.',
    'auth/email-already-in-use': 'An account already uses this email. Please sign in.',
    'auth/weak-password': 'Choose a stronger password with at least eight characters.',
    'auth/too-many-requests': 'Too many attempts. Please wait a moment and try again.',
    'auth/network-request-failed': 'Unable to connect. Check your internet connection and try again.',
  };
  return (
    messages[code] || (cause instanceof Error ? cause.message : 'Unable to complete this action. Please try again.')
  );
}
watch(
  () => auth.user,
  (account) => {
    if (account) {
      if (account.full_name) fullName.value = account.full_name;
      if (!editing.value) {
        draft.full_name = account.full_name;
        draft.phone = account.phone || '';
      }
    } else {
      editing.value = false;
      draft.full_name = '';
      draft.phone = '';
    }
  },
  { immediate: true },
);
watch(
  () => auth.user?.profile_image_url,
  () => (photoFailed.value = false),
);
onIonViewWillEnter(async () => {
  if (auth.user) {
    try {
      await auth.refreshProfile();
    } catch (cause) {
      message.value = errorMessage(cause);
    }
  }
});
async function perform(task: () => Promise<unknown>, success: string) {
  busy.value = true;
  message.value = '';
  try {
    await task();
    message.value = success;
  } catch (cause) {
    message.value = errorMessage(cause);
  } finally {
    busy.value = false;
  }
}
async function submit() {
  if ((register.value || needsCompletion.value) && !fullName.value.trim()) {
    message.value = 'Enter your full name.';
    return;
  }
  if (register.value && !needsCompletion.value && password.value !== confirmPassword.value) {
    message.value = 'Your passwords do not match.';
    return;
  }
  busy.value = true;
  message.value = '';
  try {
    const details = { full_name: fullName.value.trim(), account_type: accountType.value };
    if (needsCompletion.value) await auth.completeRegistration(details);
    else
      await auth.authenticate(email.value.trim(), password.value, register.value, register.value ? details : undefined);
    password.value = '';
    confirmPassword.value = '';
    register.value = false;
  } catch (cause) {
    message.value = errorMessage(cause);
  } finally {
    busy.value = false;
  }
}
function startEditing() {
  draft.full_name = auth.user?.full_name || '';
  draft.phone = auth.user?.phone || '';
  editing.value = true;
  message.value = '';
}
async function saveDetails() {
  await perform(async () => {
    await auth.saveProfile({ ...draft, full_name: draft.full_name.trim(), phone: draft.phone.trim() });
    editing.value = false;
  }, 'Your profile has been updated.');
}
async function uploadPhoto(event: Event) {
  const photo = (event.target as HTMLInputElement).files?.[0];
  if (!photo) return;
  if (photo.size > 5 * 1024 * 1024) {
    message.value = 'Choose a photo smaller than 5 MB.';
    if (photoInput.value) photoInput.value.value = '';
    return;
  }
  await perform(() => auth.savePhoto(photo), 'Your profile photo has been updated.');
  if (photoInput.value) photoInput.value.value = '';
}
async function reset() {
  const address = auth.user?.email || email.value.trim();
  if (!address) {
    message.value = 'Enter your email address first.';
    return;
  }
  await perform(() => auth.reset(address), 'If an account exists, a password reset email will arrive shortly.');
}
async function verification() {
  await perform(() => auth.resendVerification(), 'Verification email sent. Please check your inbox.');
}
async function checkVerification() {
  await perform(() => auth.refreshProfile(true), '');
  if (!message.value)
    message.value = auth.user?.email_verified_at
      ? 'Your email is verified.'
      : 'Your email is still awaiting verification. Please check your inbox.';
}
async function logout() {
  await perform(() => auth.logout(), '');
}
async function retry() {
  await perform(() => auth.synchronize(), '');
}
</script>
<style scoped>
.account {
  max-width: 1000px;
}
.account-intro {
  max-width: 620px;
  margin: 10px auto 28px;
}
.account-intro h1 {
  font-size: 44px;
}
.auth-form {
  max-width: 620px;
  margin: auto;
}
.auth-form h2 {
  font-size: 28px;
}
.auth-actions {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 8px;
}
.account-types {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  border: 0;
  padding: 0;
  margin: 0 0 20px;
}
.account-types legend {
  font-size: 13px;
  margin-bottom: 12px;
}
.type-option {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px 16px;
  border: 1px solid #e5dce2;
  border-radius: 12px;
  cursor: pointer;
  background: #fff;
}
.type-option.selected {
  border-color: #512b46;
  background: #f7f1f5;
  box-shadow: 0 0 0 1px #512b46;
}
.type-option input {
  position: absolute;
  right: 12px;
  top: 12px;
  accent-color: #512b46;
}
.type-option ion-icon {
  font-size: 26px;
  color: #512b46;
}
.type-option strong {
  font-size: 14px;
}
.type-option span {
  font-size: 12px;
  color: #81727d;
  line-height: 1.6;
}
.profile-hero {
  border: 1px solid #e8e0e5;
  border-radius: 20px;
  overflow: hidden;
  background: white;
  margin-bottom: 24px;
}
.profile-cover {
  height: 112px;
  background: linear-gradient(120deg, #3b2136, #704762);
  padding: 24px 28px;
  display: flex;
  justify-content: space-between;
}
.profile-cover .eyebrow {
  color: #e4c997;
  font-size: 10px;
}
.profile-cover ion-icon {
  color: #d7ba85;
  font-size: 28px;
}
.profile-summary {
  display: flex;
  align-items: center;
  gap: 22px;
  padding: 0 28px 28px;
  margin-top: -28px;
  position: relative;
  flex-wrap: wrap;
}
.avatar {
  width: 112px;
  height: 112px;
  border-radius: 50%;
  border: 5px solid white;
  background: #ece3eb;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  font-family: Georgia, serif;
  font-size: 34px;
  color: #512b46;
  overflow: hidden;
}
.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.summary-copy {
  flex: 1;
  min-width: 160px;
  padding-top: 40px;
}
.summary-copy h1 {
  font-size: 36px;
  margin: 10px 0 5px;
  overflow-wrap: anywhere;
}
.summary-copy p {
  margin: 0;
  font-size: 14px;
  overflow-wrap: anywhere;
}
.role-badge {
  background: #f3ede0;
  color: #876a37;
  font-size: 10px;
  padding: 6px 10px;
  border-radius: 20px;
  letter-spacing: 0.6px;
}
.profile-grid {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  gap: 22px;
}
.profile-side {
  display: flex;
  flex-direction: column;
  gap: 22px;
}
.card-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 14px;
}
.card-heading h2,
.profile-side h2 {
  font-size: 24px;
  margin: 0;
}
.card-heading > ion-icon {
  font-size: 24px;
  color: #9a8054;
}
.detail-list {
  margin: 0;
}
.detail-list > div {
  padding: 18px 0;
  border-bottom: 1px solid #eee7ec;
}
.detail-list > div:last-child {
  border: 0;
}
.detail-list dt {
  font-size: 11px;
  letter-spacing: 0.5px;
  color: #8d7e87;
  margin-bottom: 7px;
}
.detail-list dd {
  margin: 0;
  font-size: 14px;
  overflow-wrap: anywhere;
}
.status-pill {
  font-size: 11px;
  color: #386b53;
  background: #e8f2ec;
  padding: 5px 9px;
  border-radius: 20px;
}
.verification {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #926d35;
  margin: 14px 0;
}
.verification.verified {
  color: #386b53;
}
.verification ion-icon {
  font-size: 20px;
}
.profile-side .panel > p {
  font-size: 13px;
}
.signout-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin: 22px 0;
}
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
@media (max-width: 700px) {
  .profile-grid {
    grid-template-columns: 1fr;
  }
  .profile-summary {
    gap: 14px;
    padding: 0 20px 24px;
  }
  .avatar {
    width: 88px;
    height: 88px;
    font-size: 28px;
  }
  .summary-copy {
    padding-top: 42px;
  }
  .summary-copy h1 {
    font-size: 28px;
  }
  .account-intro h1 {
    font-size: 38px;
  }
  .profile-cover {
    height: 104px;
    padding: 22px;
  }
  .signout-row {
    flex-wrap: wrap;
  }
  .type-option {
    padding: 18px 12px;
  }
}
@media (max-width: 360px) {
  .account-types {
    grid-template-columns: 1fr;
  }
  .summary-copy {
    min-width: 140px;
  }
  .summary-copy h1 {
    font-size: 25px;
  }
}
</style>
