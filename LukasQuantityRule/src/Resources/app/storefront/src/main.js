import LukasKonfigurator from './lukas-konfigurator/lukas-konfigurator';

document.addEventListener('DOMContentLoaded', () => {
  try {
    const inst = new LukasKonfigurator();
    inst.init();
  } catch (e) {
    console.error('LukasKonfigurator init failed', e);
  }
});
