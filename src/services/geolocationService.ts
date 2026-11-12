import { Capacitor } from '@capacitor/core';
import { Geolocation } from '@capacitor/geolocation';

export async function currentLocation(): Promise<{ latitude: number; longitude: number }> {
  if (Capacitor.isNativePlatform()) {
    const permission = await Geolocation.requestPermissions();
    if (permission.location === 'denied') throw new Error('Location access was denied. You can still browse the map.');
    return (await Geolocation.getCurrentPosition({ timeout: 15000 })).coords;
  }
  if (!navigator.geolocation) throw new Error('Location is unavailable. You can still browse the map.');
  return new Promise((resolve, reject) =>
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve(coords),
      () => reject(new Error('Unable to get your location. Check permissions or browse the map manually.')),
      { timeout: 15000, maximumAge: 60000 },
    ),
  );
}
