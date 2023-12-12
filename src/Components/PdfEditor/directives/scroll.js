import throttle from 'lodash/throttle';

function inserted(el, binding) {
  const callback = binding.value;
  if (binding.modifiers.immediate) {
    callback();
  }
  const throttledScroll = throttle(callback, 300);
  el.addEventListener('scroll', throttledScroll, true);
}

export default {
  inserted,
};
