export function openDirections(latitude: number, longitude: number): void {
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
  window.open(`https://www.openstreetmap.org/directions?to=${latitude},${longitude}`, '_blank', 'noopener,noreferrer');
}
