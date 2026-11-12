import {
  onAuthStateChanged,
  signInWithEmailAndPassword,
  createUserWithEmailAndPassword,
  sendEmailVerification,
  sendPasswordResetEmail,
  signOut,
} from '@firebase/auth';
import { defineStore } from 'pinia';
import { ref } from 'vue';

import { api } from '../services/api';
import { firebaseAuth } from '../services/firebase';
import type { AppUser, RegistrationDetails } from '../types';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AppUser | null>(null),
    error = ref(''),
    notice = ref(''),
    ready = ref(false),
    processing = ref(false),
    hasIdentity = ref(false);
  let initialized = false;
  let activeUid: string | null = null;
  let synchronization: { uid: string; promise: Promise<AppUser> } | null = null;

  async function synchronize(): Promise<AppUser> {
    const uid = firebaseAuth?.currentUser?.uid;
    if (!uid) throw new Error('Please sign in first.');
    if (synchronization?.uid === uid) return synchronization.promise;
    const promise: Promise<AppUser> = api<AppUser>('auth/sync', { method: 'POST' })
      .then((account) => {
        if (firebaseAuth?.currentUser?.uid === uid) {
          user.value = account;
          error.value = '';
        }
        return account;
      })
      .finally(() => {
        if (synchronization?.promise === promise) synchronization = null;
      });
    synchronization = { uid, promise };
    return promise;
  }

  function initialize() {
    if (initialized) return;
    initialized = true;
    if (!firebaseAuth) {
      ready.value = true;
      return;
    }
    onAuthStateChanged(firebaseAuth, async (identity) => {
      hasIdentity.value = Boolean(identity);
      if (activeUid !== (identity?.uid ?? null)) {
        user.value = null;
        error.value = '';
        activeUid = identity?.uid ?? null;
      }
      if (processing.value) {
        ready.value = true;
        return;
      }
      ready.value = false;
      try {
        if (identity) await synchronize();
      } catch (cause) {
        error.value = cause instanceof Error ? cause.message : 'Unable to load your account.';
      } finally {
        ready.value = true;
      }
    });
  }

  async function completeRegistration(details: RegistrationDetails) {
    const uid = firebaseAuth?.currentUser?.uid;
    const account = await api<AppUser>('auth/complete-registration', { method: 'POST', body: JSON.stringify(details) });
    if (uid && firebaseAuth?.currentUser?.uid === uid) user.value = account;
    error.value = '';
  }

  async function authenticate(email: string, password: string, register = false, details?: RegistrationDetails) {
    if (!firebaseAuth) throw new Error('Account services are not configured yet.');
    if (register && !details?.full_name.trim()) throw new Error('Enter your name and choose an account type.');
    processing.value = true;
    notice.value = '';
    error.value = '';
    try {
      if (register) await createUserWithEmailAndPassword(firebaseAuth, email, password);
      else await signInWithEmailAndPassword(firebaseAuth, email, password);
      await synchronize();
      if (register && details) {
        await completeRegistration(details);
        try {
          await sendEmailVerification(firebaseAuth.currentUser!);
          notice.value = 'Check your email to verify your account.';
        } catch {
          notice.value = 'Your account is ready. Use Resend verification below to request a verification email.';
        }
      }
    } finally {
      processing.value = false;
    }
  }

  async function refreshProfile(refreshIdentity = false) {
    if (!firebaseAuth?.currentUser) return;
    if (refreshIdentity) {
      await firebaseAuth.currentUser.reload();
      await firebaseAuth.currentUser.getIdToken(true);
    }
    const uid = firebaseAuth.currentUser.uid;
    const account = await api<AppUser>('auth/me');
    if (firebaseAuth.currentUser?.uid === uid) user.value = account;
  }

  async function saveProfile(details: { full_name: string; phone: string }) {
    const uid = firebaseAuth?.currentUser?.uid;
    const account = await api<AppUser>('profile', { method: 'PATCH', body: JSON.stringify(details) });
    if (uid && firebaseAuth?.currentUser?.uid === uid) user.value = account;
  }

  async function savePhoto(photo: File) {
    const uid = firebaseAuth?.currentUser?.uid;
    const body = new FormData();
    body.append('photo', photo);
    const account = await api<AppUser>('profile/photo', { method: 'POST', body });
    if (uid && firebaseAuth?.currentUser?.uid === uid) user.value = account;
  }

  async function resendVerification() {
    if (!firebaseAuth?.currentUser) throw new Error('Please sign in first.');
    await sendEmailVerification(firebaseAuth.currentUser);
  }

  async function reset(email: string) {
    if (!firebaseAuth) throw new Error('Account services are not configured yet.');
    await sendPasswordResetEmail(firebaseAuth, email);
  }

  async function logout() {
    if (firebaseAuth) await signOut(firebaseAuth);
    user.value = null;
    hasIdentity.value = false;
    notice.value = '';
    error.value = '';
  }
  return {
    user,
    error,
    notice,
    ready,
    processing,
    hasIdentity,
    initialize,
    authenticate,
    completeRegistration,
    synchronize,
    refreshProfile,
    saveProfile,
    savePhoto,
    resendVerification,
    reset,
    logout,
  };
});
