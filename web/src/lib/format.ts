/**
 * Human-readable retention duration from a TTL in seconds.
 * e.g. 7776000 => "90 days", 86400 => "1 day", 3600 => "1 hour"
 */
export function formatRetention(seconds: number): string {
  if (seconds <= 0) return "0 seconds";
  const days = Math.floor(seconds / 86400);
  if (days >= 1) return `${days} ${days === 1 ? "day" : "days"}`;
  const hours = Math.floor(seconds / 3600);
  if (hours >= 1) return `${hours} ${hours === 1 ? "hour" : "hours"}`;
  const minutes = Math.floor(seconds / 60);
  if (minutes >= 1) return `${minutes} ${minutes === 1 ? "minute" : "minutes"}`;
  return `${seconds} ${seconds === 1 ? "second" : "seconds"}`;
}

/**
 * Human-readable byte size.
 * e.g. 1200 => "1.2 KB", 1500000 => "1.4 MB"
 */
export function formatBytes(b: number): string {
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / 1024 / 1024).toFixed(1)} MB`;
}
