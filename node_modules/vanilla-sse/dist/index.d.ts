//#region src/errors.d.ts
declare class SSEClientError extends Error {
  readonly code: SSEClientErrorOptions['code'];
  readonly event?: SSEClientErrorOptions['event'];
  readonly response?: SSEClientErrorOptions['response'];
  readonly state?: SSEClientErrorOptions['state'];
  constructor(message: string, options: SSEClientErrorOptions);
}
//#endregion
//#region src/types.d.ts
type SSEStatus = 'idle' | 'connecting' | 'open' | 'reconnecting' | 'closed';
type SSEErrorCode = 'aborted' | 'http' | 'network' | 'parse' | 'stream' | 'timeout';
interface SSEEnvelope<TPayload = unknown> {
  namespace: string;
  type: string;
  payload: TPayload;
  [key: string]: unknown;
}
interface RawSSEEvent {
  data: string;
  event: string;
  id?: string;
  retry?: number;
  raw: string;
}
interface SSEMessage<TPayload = unknown> extends SSEEnvelope<TPayload> {
  event: string;
  id?: string;
  receivedAt: number;
  retry?: number;
  raw: string;
}
interface SSEState {
  attempt: number;
  closeReason?: string;
  closedAt?: number;
  connected: boolean;
  errors: number;
  lastActivityAt?: number;
  lastEventId?: string;
  lastEventType?: string;
  lastNamespace?: string;
  lastType?: string;
  messages: number;
  openedAt?: number;
  reconnects: number;
  retryDelay: number;
  status: SSEStatus;
}
interface SSEHttpErrorInfo {
  status: number;
  statusText: string;
  url: string;
}
interface SSEClientErrorOptions {
  cause?: unknown;
  code: SSEErrorCode;
  event?: RawSSEEvent;
  response?: SSEHttpErrorInfo;
  state?: SSEState;
}
type SSEMessageListener<TPayload = unknown> = (message: SSEMessage<TPayload>) => void;
type SSERawEventListener = (event: RawSSEEvent) => void;
type SSEStatusListener = (state: SSEState) => void;
type SSEErrorListener = (error: SSEClientError) => void;
type SSEUnsubscribe = () => void;
interface SSEBackoffOptions {
  factor?: number;
  jitter?: boolean;
  maxDelay?: number;
  minDelay?: number;
}
interface CreateSSEOptions<TPayload = unknown> {
  autoConnect?: boolean;
  body?: BodyInit | null;
  credentials?: RequestCredentials;
  fetch?: typeof fetch;
  headers?: HeadersInit;
  lastEventId?: string;
  lastEventIdHeader?: string;
  maxRetries?: number;
  method?: string;
  parse?: (data: string, event: RawSSEEvent) => SSEEnvelope<TPayload> | null | undefined;
  reconnect?: boolean;
  retry?: number;
  retryBackoff?: SSEBackoffOptions;
  signal?: AbortSignal;
  timeout?: number;
  url: string | URL;
}
interface SSEClient<TPayload = unknown> {
  readonly state: SSEState;
  readonly status: SSEStatus;
  close(reason?: string): void;
  connect(): Promise<void>;
  getState(): SSEState;
  on(event: 'close', listener: SSEStatusListener): SSEUnsubscribe;
  on(event: 'error', listener: SSEErrorListener): SSEUnsubscribe;
  on(event: 'message', listener: SSEMessageListener<TPayload>): SSEUnsubscribe;
  on(event: 'open', listener: SSEStatusListener): SSEUnsubscribe;
  on(event: 'reconnect', listener: SSEStatusListener): SSEUnsubscribe;
  on(event: 'status', listener: SSEStatusListener): SSEUnsubscribe;
  onEvent(event: string, listener: SSERawEventListener): SSEUnsubscribe;
  reconnect(): Promise<void>;
  subscribe(listener: SSEMessageListener<TPayload>): SSEUnsubscribe;
  subscribe(namespace: string, listener: SSEMessageListener<TPayload>): SSEUnsubscribe;
  subscribe(namespace: string, type: string, listener: SSEMessageListener<TPayload>): SSEUnsubscribe;
}
//#endregion
//#region src/core.d.ts
declare function createSSE<TPayload = unknown>(options: CreateSSEOptions<TPayload> | string | URL): SSEClient<TPayload>;
//#endregion
export { type CreateSSEOptions, type RawSSEEvent, type SSEBackoffOptions, type SSEClient, SSEClientError, type SSEClientErrorOptions, type SSEEnvelope, type SSEErrorCode, type SSEErrorListener, type SSEHttpErrorInfo, type SSEMessage, type SSEMessageListener, type SSERawEventListener, type SSEState, type SSEStatus, type SSEStatusListener, type SSEUnsubscribe, createSSE };