export interface Shop {
  id: number;
  shop_name: string;
  description?: string;
  address: string;
  latitude: number;
  longitude: number;
  logo_url?: string;
  distance_km?: number;
}
export interface AppUser {
  id: number;
  full_name: string;
  email: string;
  role: 'customer' | 'owner' | 'admin';
  account_status: string;
  phone?: string | null;
  profile_image_url?: string | null;
  email_verified_at?: string | null;
  registration_completed_at: string | null;
  created_at?: string;
}
export interface RegistrationDetails {
  full_name: string;
  phone?: string;
  account_type: 'customer' | 'owner';
}
export interface Reservation {
  id: number;
  status: string;
  starts_at: string;
  ends_at: string;
}
