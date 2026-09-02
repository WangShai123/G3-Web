//#region src/index.d.ts
declare const nodeInspectCustom: unique symbol;
type EvictionHandler<KeyType = unknown, ValueType = unknown> = (key: KeyType, value: ValueType) => void;
type RequiredMaxSize = {
  maxSize: number;
  max?: number;
};
type RequiredLegacyMax = {
  max: number;
  maxSize?: number;
};
type LruCommonOptions<KeyType, ValueType> = {
  maxAge?: number;
  ttl?: number;
  onEviction?: EvictionHandler<KeyType, ValueType>;
};
type LruOptions<KeyType = unknown, ValueType = unknown> = (RequiredMaxSize | RequiredLegacyMax) & LruCommonOptions<KeyType, ValueType>;
type LruSetOptions = {
  maxAge?: number;
};
declare class Lru<KeyType = unknown, ValueType = unknown> extends Map<KeyType, ValueType> {
  #private;
  constructor(options?: LruOptions<KeyType, ValueType>);
  get maxSize(): number;
  get max(): number;
  get maxAge(): number;
  get ttl(): number;
  get size(): number;
  get(key: KeyType): ValueType | undefined;
  set(key: KeyType, value: ValueType, options?: LruSetOptions): this;
  has(key: KeyType): boolean;
  peek(key: KeyType): ValueType | undefined;
  delete(key: KeyType): boolean;
  clear(): void;
  resize(maxSize: number): void;
  evict(count?: number): void;
  expiresIn(key: KeyType): number | undefined;
  entriesAscending(): IterableIterator<[KeyType, ValueType]>;
  entriesDescending(): IterableIterator<[KeyType, ValueType]>;
  keys(): MapIterator<KeyType>;
  values(): MapIterator<ValueType>;
  entries(): MapIterator<[KeyType, ValueType]>;
  [Symbol.iterator](): MapIterator<[KeyType, ValueType]>;
  forEach(callback: (value: ValueType, key: KeyType, cache: Map<KeyType, ValueType>) => void, thisArgument?: unknown): void;
  get [Symbol.toStringTag](): string;
  toString(): string;
  [nodeInspectCustom](): string;
}
declare const createLru: <KeyType = unknown, ValueType = unknown>(options: LruOptions<KeyType, ValueType>) => Lru<KeyType, ValueType>;
//#endregion
export { EvictionHandler, Lru, Lru as default, LruOptions, LruSetOptions, createLru };