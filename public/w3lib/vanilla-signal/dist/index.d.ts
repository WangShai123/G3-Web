//#region src/types/public.d.ts
type Equality<T = unknown> = (previous: T, next: T) => boolean;
interface Disposable {
  dispose: () => void;
}
type Accessor<T = unknown> = (() => T) & {
  peek?: () => T;
  toJSON?: () => T;
};
type DisposableAccessor<T = unknown> = Accessor<T> & Disposable;
type MemoAccessor<T = unknown> = DisposableAccessor<T>;
type Setter<T = unknown> = (next: T | ((previous: T) => T)) => T;
type SignalTuple<T = unknown> = [Accessor<T>, Setter<T>];
type MaybeAccessor<T = unknown> = T | (() => T);
type Renderable = Node | string | number | boolean | null | undefined | readonly Renderable[] | (() => Renderable);
type Component<P = Record<string, unknown>> = (props: P) => Renderable;
interface DebugOptions {
  name?: string;
  debugName?: string;
}
interface SignalOptions<T = unknown> extends DebugOptions {
  equals?: false | Equality<T>;
}
interface EffectOptions extends DebugOptions {
  defer?: boolean;
  priority?: number;
}
interface MemoOptions<T = unknown> extends SignalOptions<T> {
  defer?: boolean;
}
interface Computation extends Disposable {
  id: number;
  type: string;
  disposed: boolean;
}
interface RootController extends Disposable {
  run: <T = unknown>(fn: () => T) => T | undefined;
}
interface ResourceState<T = unknown, E = Error> {
  data: T | undefined;
  latest: T | undefined;
  loading: boolean;
  error: E | null;
  isStale: boolean;
}
interface ResourceAccessor<T = unknown, E = Error> extends Accessor<T | undefined> {
  loading: () => boolean;
  error: () => E | null;
  latest: () => T | undefined;
}
interface ResourceFetcherContext<S = unknown> {
  value: S;
  refetching: boolean;
  signal?: AbortSignal;
}
type ResourceFetcher<T = unknown, S = undefined> = (value: S, context: ResourceFetcherContext<S>) => T | Promise<T>;
interface ResourceOptions<T = unknown, _S = unknown, E = Error> extends DebugOptions {
  initialValue?: T;
  loadingDelay?: number;
  suspense?: boolean;
  throwErrors?: boolean;
  source?: MaybeAccessor<_S>;
  onError?: (error: E) => void;
}
interface ResourceControls<T = unknown, S = unknown, E = Error> {
  state: ResourceState<T, E>;
  mutate: (value: T | ((previous: T | undefined) => T)) => void;
  reload: (value?: S) => Promise<T>;
  refetch: (value?: S) => Promise<T>;
}
interface ListOptions<T = unknown, K = unknown> extends DebugOptions {
  key?: (item: T, index: number) => K;
  fallback?: Renderable;
}
interface StoreOptions extends DebugOptions {
  strictReadonly?: boolean;
}
interface RawOptions {
  warn?: boolean;
}
interface ForProps<T = unknown, K = unknown> extends ListOptions<T, K> {
  each: MaybeAccessor<readonly T[]>;
  children?: Renderable | ((item: Accessor<T>, index: Accessor<number>) => Renderable);
}
interface ShowProps<T = unknown> {
  when: MaybeAccessor<T>;
  children?: Renderable | ((value: NonNullable<T>) => Renderable);
  fallback?: Renderable;
}
type StoreLeaf = Date | RegExp | Map<unknown, unknown> | Set<unknown> | WeakMap<object, unknown> | WeakSet<object> | Promise<unknown>;
type DeepReadonly<T> = T extends ((...args: any[]) => unknown) ? T : T extends StoreLeaf ? T : T extends readonly (infer Item)[] ? ReadonlyArray<DeepReadonly<Item>> : T extends object ? { readonly [Key in keyof T]: DeepReadonly<T[Key]>; } : T;
type UnwrapStore<T> = T extends ((...args: any[]) => unknown) ? T : T extends StoreLeaf ? T : T extends readonly (infer Item)[] ? UnwrapStore<Item>[] : T extends object ? { -readonly [Key in keyof T]: UnwrapStore<T[Key]>; } : T;
type AccessorValue<T> = T extends (() => infer Value) ? Value : T;
type AccessorValues<T extends readonly unknown[]> = T extends readonly [] ? readonly [] : T extends readonly [infer First, ...infer Rest] ? readonly [AccessorValue<First>, ...AccessorValues<Rest>] : readonly AccessorValue<T[number]>[];
interface ElementProps<T extends Element = Element> {
  children?: Renderable;
  class?: MaybeAccessor<string | null | undefined>;
  className?: MaybeAccessor<string | null | undefined>;
  classList?: MaybeAccessor<Record<string, MaybeAccessor<boolean>>>;
  ref?: ((element: T) => void) | {
    current?: T | null;
  };
  style?: MaybeAccessor<string | Partial<CSSStyleDeclaration> | Record<string, MaybeAccessor<unknown>>>;
  [key: `on${string}`]: ((event: Event) => void) | undefined;
  [name: string]: unknown;
}
interface SignalDevtoolsEvent<T extends string = string> {
  type: T;
  timestamp: number;
  payload: Record<string, unknown>;
}
interface SignalDevtoolsHook {
  enabled?: boolean;
  emit?: (event: SignalDevtoolsEvent) => void;
  snapshot?: () => SignalGraphSnapshot;
}
interface SignalGraphSourceNode {
  id: number;
  type: 'signal' | 'memo' | 'store-key';
  name?: string;
  ownerId?: number | null;
  storeId?: number;
  key?: string;
  observers: number[];
  disposed?: boolean;
}
interface SignalGraphComputationNode {
  id: number;
  type: string;
  name?: string;
  ownerId?: number | null;
  sources: number[];
  observers: number[];
  disposed: boolean;
  lastTrigger?: SignalGraphTrigger;
}
interface SignalGraphOwnerNode {
  id: number;
  type: string;
  name?: string;
  parentId?: number | null;
  ownedIds: number[];
  disposed: boolean;
  path: string;
}
interface SignalGraphDependency {
  sourceId: number;
  computationId: number;
}
interface SignalGraphTrigger {
  sourceId?: number;
  sourceType?: string;
  name?: string;
  key?: string;
  storeId?: number;
}
interface SignalGraphSnapshot {
  version: number;
  sources: SignalGraphSourceNode[];
  computations: SignalGraphComputationNode[];
  owners: SignalGraphOwnerNode[];
  dependencies: SignalGraphDependency[];
}
//#endregion
//#region src/types/internal.d.ts
interface OwnerNode {
  ownerId: number;
  type: string;
  name?: string;
  parent?: OwnerNode | null;
  owner?: OwnerNode | null;
  owned: any[];
  cleanups: Array<() => void>;
  disposed: boolean;
  errorHandler: ((error: any) => void) | null;
  [key: string]: any;
}
//#endregion
//#region src/core/runtime.d.ts
/**
 * 访问给定值，如果该值是访问器函数则执行它，否则直接返回原值。
 *
 * @param {*} value - 需要访问的值，可能是一个普通值或一个无参的访问器函数。
 * @returns {*} 如果 value 是访问器函数，则返回其执行结果；否则返回 value 本身。
 */
declare function access<T = any>(value: MaybeAccessor<T>): T;
/**
 * 获取当前的 Owner 对象。
 *
 * @returns {Object} 返回全局或模块作用域中的 Owner 变量。
 */
declare function getOwner(): OwnerNode | null;
declare function createSignal<T = any>(initial: T, options?: SignalOptions<T>): SignalTuple<T>;
/**
 * 创建一个响应式副作用。
 *
 * effect 会立即执行一次并自动追踪执行期间读取的 signal/store；依赖变化后会被重新调度。
 *
 * @param {Function} fn - 副作用函数。
 * @param {Object} [options={}] - effect 配置。
 * @param {boolean} [options.defer=false] - 是否延迟首次执行。
 * @param {number} [options.priority=0] - 调度优先级。
 * @returns {Object} 可 dispose 的计算节点。
 */
declare function createEffect(fn: (value?: any) => any, options?: EffectOptions): Computation;
/**
 * 创建计算型副作用。
 *
 * 这是 createEffect 的语义别名，适合表达只用于计算同步的 effect。
 *
 * @param {Function} fn - 计算函数。
 * @param {Object} [options={}] - 计算配置。
 * @returns {Object} 可 dispose 的计算节点。
 */
declare function createComputed(fn: (value?: any) => any, options?: EffectOptions): Computation;
/**
 * 创建带缓存的派生值。
 *
 * memo 只在依赖变化后重新计算，并在缓存值变化时通知读取它的下游计算。
 *
 * @param {Function} fn - 派生计算函数。
 * @param {*} [initial] - 初始缓存值；也可传 options 对象。
 * @param {Object} [options={}] - memo 配置。
 * @returns {Function} memo 读取函数。
 */
declare function createMemo<T>(fn: (previous?: T) => T, options?: MemoOptions<T>): MemoAccessor<T>;
declare function createMemo<T>(fn: (previous?: T) => T, initial: T, options?: MemoOptions<T>): MemoAccessor<T>;
/**
 * 监听一个或多个数据源，并在源值变化时调用回调。
 *
 * 回调通过 untrack 执行，因此回调内部读取的其它 signal 不会成为 watch 依赖。
 *
 * @param {Function|Function[]} source - 单个访问器或访问器数组。
 * @param {Function} fn - 变化回调，接收新值和旧值。
 * @param {Object} [options={}] - watch 配置。
 * @param {boolean} [options.defer=false] - 是否跳过首次回调。
 * @returns {Object} 底层 effect 计算节点。
 */
declare function createWatch<T>(source: Accessor<T>, fn: (next: T, previous: T | undefined) => void, options?: EffectOptions): Computation;
declare function createWatch<T extends readonly Accessor<any>[]>(source: readonly [...T], fn: (next: AccessorValues<T>, previous: AccessorValues<T> | undefined) => void, options?: EffectOptions): Computation;
declare function createWatch<T>(source: MaybeAccessor<T>, fn: (next: T, previous: T | undefined) => void, options?: EffectOptions): Computation;
/**
 * 创建选择器函数，用于快速判断某个 key 是否等于当前选中值。
 *
 * 常用于列表项选中状态，只让匹配项和取消匹配项更新。
 *
 * @param {Function|*} source - 当前选中值或其访问器。
 * @param {Function} [equals=Object.is] - key 比较函数。
 * @returns {Function} 接收 key 并返回是否匹配的函数。
 */
declare function createSelector<T = any>(source: MaybeAccessor<T>, equals?: (a: any, b: any) => boolean): (key: T) => boolean;
/**
 * 批量执行多次状态更新。
 *
 * batch 内的更新会推迟队列刷新，直到最外层 batch 结束后统一调度。
 *
 * @param {Function} fn - 批处理函数。
 * @returns {*} fn 的返回值。
 */
declare function batch<T = any>(fn: () => T): T;
/**
 * 在不收集依赖的环境中执行函数。
 *
 * 适合在 effect/watch 中读取辅助状态，但不希望这些读取触发重跑。
 *
 * @param {Function} fn - 要执行的函数。
 * @returns {*} fn 的返回值。
 */
declare function untrack<T = any>(fn: () => T): T;
/**
 * 同步刷新普通 effect 队列。
 *
 * 如果传入函数，会先在 batch 中执行该函数，再立即刷新普通队列。
 *
 * @param {Function} [fn] - 可选的同步更新函数。
 * @returns {*} fn 的返回值。
 */
declare function flushSync<T = any>(fn?: () => T): T | undefined;
/**
 * 在 transition 上下文中执行低优先级更新。
 *
 * transition 内被触发的计算会进入 transition 队列，稍后在空闲时刷新。
 *
 * @param {Function} fn - transition 回调。
 * @returns {*} fn 的返回值。
 */
declare function startTransition<T = any>(fn: () => T): T;
declare function onCleanup<T extends () => void>(fn: T): T;
/**
 * 注册销毁回调。
 *
 * 这是 onCleanup 的语义别名，用于表达资源释放意图。
 *
 * @param {Function} fn - 销毁回调。
 * @returns {Function} 原始回调。
 */
declare function onDispose<T extends () => void>(fn: T): T;
/**
 * 在当前同步执行结束后的微任务中运行挂载回调。
 *
 * 回调会尝试恢复创建它时的 Owner 上下文，若 owner 已销毁则不会执行。
 *
 * @param {Function} fn - 挂载回调。
 * @returns {void}
 */
declare function onMount(fn: () => void): void;
/**
 * 创建一个可手动 dispose 的响应式作用域。
 *
 * 作用域可用于将若干 effect、memo 和清理函数绑定到同一个生命周期。
 *
 * @param {Function} [fn] - 创建作用域后立即执行的函数。
 * @returns {Object} 包含 result、dispose 和 run 的作用域对象。
 */
declare function createScope<T = any>(fn?: () => T, options?: DebugOptions): {
  result: T | undefined;
  dispose: () => void;
  run: <R = any>(fn: () => R) => R | undefined;
};
/**
 * 创建响应式根作用域。
 *
 * 根作用域不依赖外层组件系统，适合手动挂载一组响应式资源并返回 dispose。
 *
 * @param {Function} fn - 根作用域回调，接收 dispose 函数。
 * @returns {*} fn 的返回值；如果返回 undefined，则返回默认作用域控制对象。
 */
declare function createRoot<T = any>(fn: (dispose: () => void) => T, options?: DebugOptions): T extends void ? RootController : T;
declare function createErrorBoundary(fn: () => void, fallback?: any): {
  error: Accessor<any>;
  fallback: any;
  hasError: () => boolean;
  reset: () => void;
  dispose: () => void;
};
/**
 * 立即执行函数并捕获同步错误。
 *
 * 与 createErrorBoundary 不同，它不创建响应式作用域，只处理当前调用栈里的异常。
 *
 * @param {Function} fn - 需要保护执行的函数。
 * @param {*|Function} fallback - 错误发生时返回的值或错误映射函数。
 * @returns {*} fn 的结果或 fallback 结果。
 */
declare function catchError<T = any>(fn: () => T, fallback: T | ((error: any) => T)): T;
//#endregion
//#region src/store/proxy.d.ts
declare function isStore(value: unknown): boolean;
declare function isReadonlyStore(value: unknown): boolean;
declare function raw<T = any>(value: T, options?: RawOptions): T;
declare function storeVersion(value: unknown): number;
/**
 * 深度解包 store proxy，生成普通对象或数组快照。
 *
 * 使用 WeakMap 处理循环引用，避免递归死循环。
 *
 * @param {*} value - 需要解包的值。
 * @param {WeakMap} [seen=new WeakMap()] - 循环引用缓存。
 * @returns {*} 解包后的普通值。
 */
declare function unwrap<T = any>(value: T, seen?: WeakMap<object, any>): UnwrapStore<T>;
/**
 * 创建 store 当前状态的普通对象快照。
 *
 * @param {*} value - store、数组或普通值。
 * @returns {*} 解包后的快照。
 */
declare function snapshot$1<T = any>(value: T): UnwrapStore<T>;
/**
 * 创建浅层响应式 store。
 *
 * 只有第一层属性会被代理；嵌套对象保持原样。
 *
 * @param {Object|Array} [target={}] - 初始对象或数组。
 * @returns {*} 响应式 store proxy。
 */
declare function createStore<T extends object = Record<string, any>>(target?: T, options?: StoreOptions): T;
/**
 * 创建深层响应式 store。
 *
 * 嵌套对象和数组会在读取时懒代理。
 *
 * @param {Object|Array} [target={}] - 初始对象或数组。
 * @returns {*} 深层响应式 store proxy。
 */
declare function createDeepStore<T extends object = Record<string, any>>(target?: T, options?: StoreOptions): T;
/**
 * 创建深层只读 store。
 *
 * 读取仍会被追踪，但写入、删除和数组变异会被阻止。
 *
 * @param {Object|Array} [target={}] - 初始对象或数组。
 * @returns {*} 只读 store proxy。
 */
declare function createReadonly<T extends object = Record<string, any>>(target?: T, options?: StoreOptions): DeepReadonly<T>;
/**
 * 在 batch 中对 store 执行可变更新。
 *
 * 该函数不会复制数据，只是把多次写入合并为一次刷新时机。
 *
 * @param {*} store - 需要更新的 store。
 * @param {Function} recipe - 直接修改 store 的函数。
 * @returns {*} 原 store。
 */
declare function produce<T>(store: T, recipe: (store: T) => void): T;
//#endregion
//#region src/async/resource.d.ts
declare function createResource<T, E = Error>(fetcher: ResourceFetcher<T, undefined>, options?: ResourceOptions<T, undefined, E>): [ResourceAccessor<T, E>, ResourceControls<T, undefined, E>];
declare function createResource<T, S, E = Error>(source: MaybeAccessor<S>, fetcher: ResourceFetcher<T, S>, options?: ResourceOptions<T, S, E>): [ResourceAccessor<T, E>, ResourceControls<T, S, E>];
//#endregion
//#region src/async/suspense.d.ts
/**
 * 创建简单的 suspense memo。
 *
 * 当 fn 抛出 Promise 时返回 fallback，并在 Promise settle 后触发重新计算。
 *
 * @param {Function} fn - 可能抛出 Promise 的读取函数。
 * @param {*|Function} fallback - pending 时返回的兜底值或访问器。
 * @returns {Function} memo 读取函数。
 */
declare function createSuspense<T = any>(fn: () => T, fallback: MaybeAccessor<T>): Accessor<T>;
//#endregion
//#region src/dom/insert.d.ts
/**
 * 将可渲染值插入到父节点中。
 *
 * 如果 value 是访问器，会创建 effect 自动更新 DOM，并返回清理函数。
 *
 * @param {Node} parent - 父节点。
 * @param {*} value - 可渲染值或访问器。
 * @param {Node|null} [marker=null] - 插入位置标记，节点会插入在该标记前。
 * @returns {Function} 清理函数。
 */
declare function insert(parent: Node, value: any, marker?: Node | null): () => void;
/**
 * 渲染内容到容器中。
 *
 * 渲染前会清空容器，并在新的 root 作用域中建立响应式 DOM 更新。
 *
 * @param {*} value - 可渲染值或访问器。
 * @param {Element} container - DOM 容器。
 * @returns {Function} root dispose 函数。
 */
declare function render(value: any, container: Element): any;
//#endregion
//#region src/dom/bindings.d.ts
/**
 * 将文本节点内容绑定到 signal。
 *
 * @param {Element} el - 目标元素。
 * @param {*|Function} signal - 文本值或访问器。
 * @returns {Object} effect 计算节点。
 */
declare function bindText(el: Element, signal: MaybeAccessor<any>): Computation;
/**
 * 将元素属性绑定到 signal。
 *
 * null/false 会移除属性，true 会设置布尔属性，其它值会转为字符串。
 *
 * @param {Element} el - 目标元素。
 * @param {string} name - 属性名。
 * @param {*|Function} signal - 属性值或访问器。
 * @returns {Object} effect 计算节点。
 */
declare function bindAttr(el: Element, name: string, signal: MaybeAccessor<any>): Computation;
/**
 * 将元素样式绑定到 signal 或样式对象。
 *
 * name 为对象时会批量设置 style；否则只绑定单个样式属性。
 *
 * @param {HTMLElement|SVGElement} el - 目标元素。
 * @param {string|Object} name - 样式名或样式对象。
 * @param {*|Function} signal - 单个样式值或访问器。
 * @returns {Object} effect 计算节点。
 */
declare function bindStyle(el: any, name: string | MaybeAccessor<Record<string, any>>, signal?: MaybeAccessor<any>): Computation;
/**
 * 根据 signal 切换元素 class。
 *
 * @param {Element} el - 目标元素。
 * @param {string} name - class 名称。
 * @param {*|Function} signal - 布尔值或访问器。
 * @returns {Object} effect 计算节点。
 */
declare function bindClass(el: Element, name: string, signal: MaybeAccessor<any>): Computation;
/**
 * 根据 signal 控制元素 display。
 *
 * falsy 时设置为 none，truthy 时恢复为传入的 display 值。
 *
 * @param {HTMLElement|SVGElement} el - 目标元素。
 * @param {*|Function} signal - 显隐布尔值或访问器。
 * @param {string} [display=''] - 显示时使用的 display 值。
 * @returns {Object} effect 计算节点。
 */
declare function bindShow(el: HTMLElement | SVGElement, signal: MaybeAccessor<any>, display?: string): Computation;
/**
 * 在锚点附近按条件挂载或销毁一段 DOM。
 *
 * factory 只在条件变为 truthy 时执行，块级内容会绑定到独立 root 作用域。
 *
 * @param {Node} anchor - 条件块锚点。
 * @param {*|Function} condition - 条件值或访问器。
 * @param {Function} factory - 创建块内容的函数。
 * @returns {Function} 清理函数。
 */
declare function bindIf(anchor: Node, condition: MaybeAccessor<any>, factory: () => any): () => void;
//#endregion
//#region src/dom/list.d.ts
/**
 * 将数组列表绑定到 DOM。
 *
 * 通过 key 复用已有节点，列表项会获得 item 和 index 的响应式访问器。
 *
 * @param {Node} anchor - 列表插入锚点。
 * @param {Array|Function} listSignal - 数组或数组访问器。
 * @param {Function} renderItem - 渲染单项的函数。
 * @param {Object} [options={}] - 列表配置。
 * @returns {Function} 清理函数。
 */
declare function bindList<T>(anchor: Node, listSignal: MaybeAccessor<readonly T[]>, renderItem: (item: T, index: Accessor<number>, itemAccessor: Accessor<T>) => Renderable, options?: ListOptions<T>): () => void;
/**
 * 创建基于单个属性的列表 key 函数。
 *
 * @param {string} property - 用作 key 的属性名。
 * @returns {Function} key 提取函数。
 */
declare function createListKey(property: string): (item: any) => any;
/**
 * 创建组合属性 key 函数。
 *
 * 多个属性值会用下划线连接，适合复合主键场景。
 *
 * @param {...string} properties - 参与组合的属性名。
 * @returns {Function} key 提取函数。
 */
declare function createCompositeKey(...properties: string[]): (item: any) => string;
//#endregion
//#region src/dom/control-flow.d.ts
/**
 * 条件渲染组件。
 *
 * 返回一个访问器，由 insert 或 JSX runtime 在后续渲染中消费。
 *
 * @param {Object} props - Show 参数。
 * @returns {Function} 可渲染访问器。
 */
declare function Show<T = any>(props: ShowProps<T>): Accessor<Renderable>;
/**
 * 列表渲染组件。
 *
 * 内部通过 bindList 维护 keyed DOM 记录。
 *
 * @param {Object} props - For 参数。
 * @returns {DocumentFragment} 包含列表锚点的片段。
 */
declare function For<T = any, K = any>(props: ForProps<T, K>): DocumentFragment;
//#endregion
//#region src/jsx/runtime.d.ts
/**
 * JSX/ hyperscript 工厂函数。
 *
 * type 为函数时按组件调用；type 为字符串时创建 DOM/SVG 元素并应用 props 与 children。
 *
 * @param {string|Function} type - 标签名或组件函数。
 * @param {Object} props - 属性对象。
 * @param {...*} children - 子节点。
 * @returns {*} 组件结果或 DOM 元素。
 */
declare function h<P extends object>(type: Component<P>, props?: P | null, ...children: Renderable[]): Renderable;
declare function h<K extends keyof HTMLElementTagNameMap>(type: K, props?: ElementProps<HTMLElementTagNameMap[K]> | null, ...children: Renderable[]): HTMLElementTagNameMap[K];
declare function h<K extends keyof SVGElementTagNameMap>(type: K, props?: ElementProps<SVGElementTagNameMap[K]> | null, ...children: Renderable[]): SVGElementTagNameMap[K];
/**
 * JSX classic runtime 使用的 createElement 别名。
 */
declare const createElement: typeof h;
/**
 * JSX Fragment 组件。
 *
 * @param {Object} [props={}] - Fragment props。
 * @returns {*} children 或空数组。
 */
declare function Fragment(props?: {
  children?: any;
}): any;
/**
 * JSX automatic runtime 入口。
 *
 * 同时支持被作为 tagged template 使用。
 *
 * @param {string|Function|TemplateStringsArray} type - 标签、组件或模板字符串。
 * @param {Object} props - 属性对象。
 * @param {*} key - JSX key。
 * @returns {*} 渲染结果。
 */
declare function jsx(type: TemplateStringsArray, ...values: any[]): Node | Node[];
declare function jsx<P extends object>(type: Component<P>, props?: P | null, key?: any): Renderable;
declare function jsx<K extends keyof HTMLElementTagNameMap>(type: K, props?: ElementProps<HTMLElementTagNameMap[K]> | null, key?: any): HTMLElementTagNameMap[K];
declare function jsx<K extends keyof SVGElementTagNameMap>(type: K, props?: ElementProps<SVGElementTagNameMap[K]> | null, key?: any): SVGElementTagNameMap[K];
/**
 * JSX automatic runtime 的多 children 入口。
 */
declare const jsxs: typeof jsx;
/**
 * JSX development runtime 入口。
 */
declare const jsxDEV: typeof jsx;
//#endregion
//#region src/jsx/template.d.ts
/**
 * 将 HTML 字符串解析为 DOM 节点。
 *
 * @param {string} markup - HTML 字符串。
 * @returns {Node|Node[]} 单个节点或节点数组。
 */
declare function html(markup: string): Node | Node[];
//#endregion
//#region src/utils/index.d.ts
interface DebouncedOptions<T = unknown> extends SignalOptions<T> {
  initialValue?: T;
}
interface ThrottledOptions<T = unknown> extends SignalOptions<T> {
  initialValue?: T;
  leading?: boolean;
  trailing?: boolean;
}
declare function createDebounced<T>(source: MaybeAccessor<T>, delay?: number, options?: DebouncedOptions<T>): Accessor<T>;
declare function createThrottled<T>(source: MaybeAccessor<T>, delay?: number, options?: ThrottledOptions<T>): Accessor<T>;
//#endregion
//#region src/devtools/hook.d.ts
declare global {
  interface Window {
    __SIGNAL_DEVTOOLS__?: SignalDevtoolsHook;
  }
}
declare function emit(type: string | SignalDevtoolsEvent, payload?: Record<string, unknown>): void;
declare function getDevtoolsSnapshot(): SignalGraphSnapshot;
declare const snapshot: typeof getDevtoolsSnapshot;
//#endregion
export { type Accessor, type Component, type Computation, type DebouncedOptions, type DebugOptions, type DeepReadonly, type DisposableAccessor, type EffectOptions, type ElementProps, type Equality, For, type ForProps, Fragment, type ListOptions, type MaybeAccessor, type MemoAccessor, type MemoOptions, type RawOptions, type Renderable, type ResourceAccessor, type ResourceControls, type ResourceFetcher, type ResourceOptions, type ResourceState, type RootController, type Setter, Show, type ShowProps, type SignalDevtoolsEvent, type SignalDevtoolsHook, type SignalGraphComputationNode, type SignalGraphDependency, type SignalGraphOwnerNode, type SignalGraphSnapshot, type SignalGraphSourceNode, type SignalGraphTrigger, type SignalOptions, type SignalTuple, type StoreOptions, type ThrottledOptions, type UnwrapStore, access, batch, bindAttr, bindClass, bindIf, bindList, bindShow, bindStyle, bindText, catchError, createCompositeKey, createComputed, createDebounced, createDeepStore, createEffect, createElement, createErrorBoundary, createListKey, createMemo, createReadonly, createResource, createRoot, createScope, createSelector, createSignal, createStore, createSuspense, createThrottled, createWatch, snapshot as devtoolsSnapshot, emit, flushSync, getDevtoolsSnapshot, getOwner, h, html, insert, isReadonlyStore, isStore, jsx, jsxDEV, jsxs, onCleanup, onDispose, onMount, produce, raw, render, snapshot$1 as snapshot, startTransition, storeVersion, untrack, unwrap };