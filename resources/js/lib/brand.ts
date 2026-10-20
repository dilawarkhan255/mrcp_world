// The server supplies APP_NAME at runtime; this is the single frontend brand constant.
export const APP_NAME = typeof document !== 'undefined' ? document.documentElement.dataset.appName || 'MRCP World' : 'MRCP World';
