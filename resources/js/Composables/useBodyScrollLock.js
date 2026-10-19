import { watch, onUnmounted } from 'vue';

let activeLocks = 0;

export function lockBodyScroll() {
  activeLocks++;
  if (activeLocks === 1) {
    if (typeof document !== 'undefined') {
      document.body.classList.add('modal-open');
      document.documentElement.classList.add('modal-open');
      document.body.style.overflow = 'hidden';
    }
  }
}

export function unlockBodyScroll() {
  activeLocks = Math.max(0, activeLocks - 1);
  if (activeLocks === 0) {
    if (typeof document !== 'undefined') {
      document.body.classList.remove('modal-open');
      document.documentElement.classList.remove('modal-open');
      document.body.style.overflow = '';
    }
  }
}

export function resetAllScrollLocks() {
  activeLocks = 0;
  if (typeof document !== 'undefined') {
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('modal-open');
    document.body.style.overflow = '';
  }
}

export function useBodyScrollLock(isOpenRef) {
  if (!isOpenRef) return;

  watch(
    isOpenRef,
    (isOpen) => {
      if (Boolean(isOpen)) {
        lockBodyScroll();
      } else {
        unlockBodyScroll();
      }
    },
    { immediate: true }
  );

  onUnmounted(() => {
    if (isOpenRef && Boolean(isOpenRef.value)) {
      unlockBodyScroll();
    }
  });
}
