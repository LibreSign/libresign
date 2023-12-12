import 'intersection-observer'
import debug from "debug"
const log = debug("app:directives/visible")

const instances = new WeakMap();

function createObserver(el, vnode, modifiers, callback) {
  const observer = new IntersectionObserver(entries => {
    const entry = entries[0];
    if (entry.isIntersecting) {
      callback();
      if (modifiers.once) {
        disconnectObserver(observer, el);
      }
    }
  })

  // Observe when element is inserted in DOM
  vnode.context.$nextTick(() => observer.observe(el))

  return observer;
}
function disconnectObserver(observer, el) {
  log('Disconnecting observer', el);
  if (observer) observer.disconnect();
}

function bind(el, { value, modifiers }, vnode) {
  log("Binding element", el);
  if (typeof window.IntersectionObserver === 'undefined') {
    log('IntersectionObserver API is not available in your browser.')
  } else {
    const observer = createObserver(el, vnode, modifiers, () => {
      log("Element is visible", el)
      const callback = value;
      if (typeof callback === "function") callback();
    })
    instances.set(el, {observer})
  }
}

function update(el, { value, oldValue }, vnode) {
  if (value === oldValue) return;

  const {observer} = instances.get(el)
  disconnectObserver(observer, el);
  bind(el, { value }, vnode);
}

function unbind(el) {
  if (instances.has(el)) {
    const {observer} = instances.get(el);
    disconnectObserver(observer, el);
    instances.delete(el);
  }
}

export default {
  bind,
  update,
  unbind
}
