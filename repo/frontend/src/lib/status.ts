/**
 * Centralised status string constants. The values here must match the
 * backend entity constants character-for-character — a typo on either side
 * silently breaks feature gates (e.g., download / revoke visibility in the
 * certificate UI). See `src/Entity/*.php` for the canonical list.
 *
 * NEVER hand-compare a status against a literal string in a component. Go
 * through these constants or the `Is` helpers so TypeScript can flag
 * typos at compile time.
 */

export const CertificateStatus = {
  /** Backend: App\Entity\Certificate::STATUS_ACTIVE */
  ACTIVE: 'active',
  /** Backend: App\Entity\Certificate::STATUS_REVOKED */
  REVOKED: 'revoked',
} as const;

export type CertificateStatusValue =
  (typeof CertificateStatus)[keyof typeof CertificateStatus];

export const DeviceStatus = {
  /** Backend: App\Entity\Device::STATUS_APPROVED */
  APPROVED: 'approved',
  /** Backend: App\Entity\Device::STATUS_REVOKED */
  REVOKED: 'revoked',
} as const;

export type DeviceStatusValue = (typeof DeviceStatus)[keyof typeof DeviceStatus];

export const VoucherStatus = {
  /** Backend: App\Entity\Voucher::STATUS_ACTIVE */
  ACTIVE: 'active',
  /** Backend: App\Entity\Voucher::STATUS_VOID */
  VOID: 'void',
} as const;

export type VoucherStatusValue =
  (typeof VoucherStatus)[keyof typeof VoucherStatus];

export const VoucherClaimStatus = {
  /** Backend: App\Entity\VoucherClaim::STATUS_LOCKED */
  LOCKED: 'locked',
  /** Backend: App\Entity\VoucherClaim::STATUS_REDEEMED */
  REDEEMED: 'redeemed',
  /** Backend: App\Entity\VoucherClaim::STATUS_VOID */
  VOID: 'void',
} as const;

export type VoucherClaimStatusValue =
  (typeof VoucherClaimStatus)[keyof typeof VoucherClaimStatus];

export const ResourceStatus = {
  /** Backend: App\Entity\Resource::STATUS_ACTIVE */
  ACTIVE: 'active',
  /** Backend: App\Entity\Resource::STATUS_RETIRED */
  RETIRED: 'retired',
} as const;

export type ResourceStatusValue =
  (typeof ResourceStatus)[keyof typeof ResourceStatus];

export const BookingStatus = {
  RESERVED: 'reserved',
  CONFIRMED: 'confirmed',
  CANCELLED: 'cancelled',
  EXPIRED: 'expired',
} as const;

export type BookingStatusValue = (typeof BookingStatus)[keyof typeof BookingStatus];

/**
 * True when the certificate is still valid and therefore downloadable.
 * Extracted as a named helper so the UI never has to compare the raw
 * status string, and the test harness can assert the behaviour without
 * rendering the component.
 */
export function certificateIsDownloadable(status: string): boolean {
  return status === CertificateStatus.ACTIVE;
}

/**
 * True when an admin may revoke this certificate (only active ones).
 */
export function certificateIsRevokable(status: string): boolean {
  return status === CertificateStatus.ACTIVE;
}

/**
 * True when a guardian may remotely revoke this device's session.
 */
export function deviceIsRemoteLogoutEligible(status: string): boolean {
  return status === DeviceStatus.APPROVED;
}
