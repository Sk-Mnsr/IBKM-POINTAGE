import type { route as routeFn } from 'ziggy-js';
import type { SharedProps } from './index';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof routeFn;
    }
}

declare global {
    interface BarcodeDetectorResult {
        rawValue: string;
    }

    class BarcodeDetector {
        constructor(options?: { formats: string[] });
        detect(source: HTMLVideoElement): Promise<BarcodeDetectorResult[]>;
    }
}

export {};
