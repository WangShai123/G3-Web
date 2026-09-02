//#region src/core/types.d.ts
type MaybePromise<T> = T | Promise<T>;
type HeaderValue = string | number | boolean | null | undefined;
type HeaderRecord = Record<string, HeaderValue>;
type RequestMethod = 'DELETE' | 'GET' | 'HEAD' | 'OPTIONS' | 'PATCH' | 'POST' | 'PUT';
type RequestPhase = 'setup' | 'request' | 'fetch' | 'parse' | 'status' | 'response';
type ResponseType = 'arrayBuffer' | 'auto' | 'blob' | 'formData' | 'json' | 'response' | 'text';
interface QueryContext<TQueryKey = unknown> {
  attempt: number;
  meta?: unknown;
  queryKey: TQueryKey;
  signal?: AbortSignal;
}
interface UploadProgress {
  lengthComputable: boolean;
  loaded: number;
  progress?: number;
  total?: number;
}
interface RequestContext<TBody = unknown> {
  body?: TBody;
  headers: Headers;
  init: RequestInit;
  input: string | URL;
  meta?: unknown;
  method: RequestMethod;
  options: NormalizedRequestOptions<TBody>;
  url: URL | string;
}
interface RequestResult<TData = unknown, TBody = unknown> {
  data: TData;
  request: RequestContext<TBody>;
  response: Response;
}
interface RequestError<TData = unknown, TBody = unknown> extends Error {
  cause?: unknown;
  code?: string;
  data?: TData;
  request?: RequestContext<TBody>;
  response?: Response;
  status?: number;
  statusText?: string;
}
interface RequestClientOptions {
  baseURL?: string | URL;
  fetch?: typeof fetch;
  headers?: HeadersSource;
  responseType?: ResponseType;
  searchParams?: SearchParamsSource;
  transformResponse?: ResponseTransformSource;
  validateStatus?: (status: number, response: Response) => boolean;
}
interface RequestOptions<TBody = unknown> extends Omit<RequestInit, 'body' | 'headers' | 'method'> {
  body?: BodyInit | TBody;
  data?: BodyInit | TBody;
  headers?: HeadersSource;
  meta?: unknown;
  method?: RequestMethod | Lowercase<RequestMethod>;
  onUploadProgress?: (progress: UploadProgress) => void;
  responseType?: ResponseType;
  searchParams?: SearchParamsSource;
  transformResponse?: ResponseTransformSource<TBody>;
  url?: string | URL;
  validateStatus?: (status: number, response: Response) => boolean;
}
interface NormalizedRequestOptions<TBody = unknown> extends RequestOptions<TBody> {
  method: RequestMethod;
}
type HeadersSource = HeadersInit | HeaderRecord | ((context: RequestContextDraft) => MaybePromise<HeadersInit | HeaderRecord>);
type SearchParamsSource = URLSearchParams | string | Array<[string, HeaderValue]> | Record<string, HeaderValue | HeaderValue[]> | ((context: RequestContextDraft) => MaybePromise<SearchParamsSource>);
interface RequestContextDraft {
  input: string | URL;
  meta?: unknown;
  method: RequestMethod;
}
type RequestInterceptor<TBody = unknown> = (context: RequestContext<TBody>) => MaybePromise<RequestContext<TBody> | void>;
type ResponseInterceptor<TData = unknown, TBody = unknown> = (result: RequestResult<TData, TBody>) => MaybePromise<RequestResult<TData, TBody> | TData | void>;
interface RequestErrorEvent<TBody = unknown> {
  phase: RequestPhase;
  request?: RequestContext<TBody>;
}
type ErrorInterceptor<TBody = unknown> = (error: unknown, event: RequestErrorEvent<TBody>) => MaybePromise<RequestResult<unknown, TBody> | void>;
interface ResponseTransformContext<TBody = unknown> {
  rawData: unknown;
  request: RequestContext<TBody>;
  response: Response;
  responseType: ResponseType;
}
type ResponseTransformer<TBody = unknown> = (data: unknown, context: ResponseTransformContext<TBody>) => MaybePromise<unknown>;
type ResponseTransformSource<TBody = unknown> = ResponseTransformer<TBody> | Array<ResponseTransformer<TBody>>;
interface InterceptorManager<THandler> {
  clear(): void;
  eject(id: number): void;
  use(handler: THandler): number;
}
type QueryRequestFactory<TBody = unknown, TQueryKey = unknown> = (context: QueryContext<TQueryKey>) => MaybePromise<RequestInput<TBody>>;
type RequestInput<TBody = unknown> = string | URL | RequestOptions<TBody> | [string | URL, RequestOptions<TBody>?];
interface RequestClient {
  defaults: RequestClientOptions;
  interceptors: {
    error: InterceptorManager<ErrorInterceptor>;
    request: InterceptorManager<RequestInterceptor>;
    response: InterceptorManager<ResponseInterceptor>;
  };
  delete<TData = unknown, TBody = unknown>(input: string | URL, options?: RequestOptions<TBody>): Promise<TData>;
  get<TData = unknown, TBody = unknown>(input: string | URL, options?: RequestOptions<TBody>): Promise<TData>;
  head<TData = unknown, TBody = unknown>(input: string | URL, options?: RequestOptions<TBody>): Promise<TData>;
  options<TData = unknown, TBody = unknown>(input: string | URL, options?: RequestOptions<TBody>): Promise<TData>;
  patch<TData = unknown, TBody = unknown>(input: string | URL, body?: BodyInit | TBody, options?: RequestOptions<TBody>): Promise<TData>;
  post<TData = unknown, TBody = unknown>(input: string | URL, body?: BodyInit | TBody, options?: RequestOptions<TBody>): Promise<TData>;
  put<TData = unknown, TBody = unknown>(input: string | URL, body?: BodyInit | TBody, options?: RequestOptions<TBody>): Promise<TData>;
  queryFn<TData = unknown, TBody = unknown, TQueryKey = unknown>(input: RequestInput<TBody> | QueryRequestFactory<TBody, TQueryKey>): (context: QueryContext<TQueryKey>) => Promise<TData>;
  request<TData = unknown, TBody = unknown>(input: RequestInput<TBody>, options?: RequestOptions<TBody>): Promise<TData>;
  send<TData = unknown, TBody = unknown>(input: RequestInput<TBody>, options?: RequestOptions<TBody>): Promise<RequestResult<TData, TBody>>;
}
//#endregion
//#region src/core/error.d.ts
declare function isRequestError(error: unknown): error is RequestError;
declare function createRequestError<TData = unknown, TBody = unknown>(message: string, details: Omit<RequestError<TData, TBody>, 'message' | 'name'>): RequestError<TData, TBody>;
//#endregion
//#region src/index.d.ts
declare const requestClient: RequestClient;
declare function createRequest(defaults?: RequestClientOptions): RequestClient;
declare function request<TData = unknown, TBody = unknown>(input: RequestInput<TBody>, options?: RequestOptions<TBody>): Promise<TData>;
declare function get<TData = unknown, TBody = unknown>(input: string | URL, options?: RequestOptions<TBody>): Promise<TData>;
declare function post<TData = unknown, TBody = unknown>(input: string | URL, body?: BodyInit | TBody, options?: RequestOptions<TBody>): Promise<TData>;
//#endregion
export { ErrorInterceptor, HeaderRecord, HeaderValue, HeadersSource, InterceptorManager, MaybePromise, NormalizedRequestOptions, QueryContext, QueryRequestFactory, RequestClient, RequestClientOptions, RequestContext, RequestContextDraft, RequestError, RequestErrorEvent, RequestInput, RequestInterceptor, RequestMethod, RequestOptions, RequestPhase, RequestResult, ResponseInterceptor, ResponseTransformContext, ResponseTransformSource, ResponseTransformer, ResponseType, SearchParamsSource, UploadProgress, createRequest, createRequestError, get, isRequestError, post, request, requestClient };