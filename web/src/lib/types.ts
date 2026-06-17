export interface LogDocument {
  _id: string;
  content?: string;
  content_file_id?: string | null;
  source?: string;
  metadata?: MetadataItem[];
  token?: string;
  created: Date;
  accessed: Date;
  analysis?: AnalysisData | null;
}

export interface MetadataItem {
  label: string;
  value: string;
}

export interface AnalysisData {
  detected: string;
  title: string;
  lines: number;
  errors: number;
  entries: LogEntry[];
  problems: ProblemData[];
  information: InfoItem[];
  gated?: GatedRow[];
}

// Compact: line text is NOT carried here. The client rebuilds each entry's
// text from the raw `content` using first..last, so the stored/shipped
// analysis stays small. See analyze.php and LogView.buildItems.
export interface LogEntry {
  level: string;
  level_int: number;
  prefix: string | null;
  first: number;
  last: number;
}

export interface ProblemData {
  message: string;
  severity: string;
  count: number;
  entry_line: number | null;
  is_noise: boolean;
  kind: string;
  attribution: string;
  rank: number;
  gated: boolean;
  stack_trace?: string;
  solutions: { message: string }[];
  mod?: {
    name: string;
    workshop_id: string | null;
    confidence: string;
    is_direct: boolean;
  };
}

export interface GatedRow {
  fingerprint: string;
  occurrences: number;
  reason: string;
  kind: string;
  sampleMessage: string;
}

export interface InfoItem {
  label: string;
  value: string;
}

export interface CreateLogRequest {
  content: string;
  source?: string;
  metadata?: MetadataItem[];
}

export interface CreateLogResponse {
  success: boolean;
  id: string;
  url: string;
  error?: string;
}

export interface LogPageData {
  id: string;
  title: string;
  description: string;
  displayUrl: string;
  url: string;
  rawUrl: string;
  content: string;
  source?: string;
  created?: number;
  metadata: MetadataItem[];
  errors: number;
  errorsString: string;
  lines: number;
  linesString: string;
  hasErrors: boolean;
  hasValidToken: boolean;
  analysis?: AnalysisData | null;
}
