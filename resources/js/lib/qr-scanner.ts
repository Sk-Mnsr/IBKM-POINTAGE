type Decoder = (image: ImageData) => string | null;

let decoder: Decoder | null = null;

/**
 * jsQR is only downloaded when a scan starts, so the punch page stays light.
 */
async function loadDecoder(): Promise<Decoder> {
    if (decoder === null) {
        const { default: jsQR } = await import('jsqr');
        decoder = (image) => jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' })?.data ?? null;
    }

    return decoder;
}

function readPixels(source: CanvasImageSource, width: number, height: number, maxSize: number): ImageData {
    const scale = Math.min(1, maxSize / Math.max(width, height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    const context = canvas.getContext('2d', { willReadFrequently: true })!;
    context.drawImage(source, 0, 0, canvas.width, canvas.height);

    return context.getImageData(0, 0, canvas.width, canvas.height);
}

function loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('image'));
        };
        image.src = url;
    });
}

/**
 * Reads a QR code from a photo taken with the phone camera. Works without HTTPS.
 */
export async function decodeQrFromPhoto(file: File): Promise<string | null> {
    const [decode, image] = await Promise.all([loadDecoder(), loadImage(file)]);

    for (const size of [1200, 800, 1600]) {
        const result = decode(readPixels(image, image.naturalWidth, image.naturalHeight, size));

        if (result) {
            return result;
        }
    }

    return null;
}

export function canScanLive(): boolean {
    return window.isSecureContext && typeof navigator.mediaDevices?.getUserMedia === 'function';
}

export interface LiveScan {
    stop: () => void;
}

/**
 * Streams the rear camera into the video element and resolves each frame until a QR code is found.
 * Uses the native BarcodeDetector when the browser has one, jsQR otherwise (Safari, iPhone).
 */
export async function startLiveScan(video: HTMLVideoElement, onResult: (value: string) => void): Promise<LiveScan> {
    const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
    });

    let active = true;
    const stop = (): void => {
        active = false;
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    };

    video.srcObject = stream;
    video.setAttribute('playsinline', 'true');
    await video.play();

    const native = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
    const decode = native ? null : await loadDecoder();

    const scanFrame = async (): Promise<void> => {
        if (!active) {
            return;
        }

        if (video.readyState >= 2 && video.videoWidth > 0) {
            const value = native
                ? (await native.detect(video))[0]?.rawValue ?? null
                : decode!(readPixels(video, video.videoWidth, video.videoHeight, 720));

            if (value && active) {
                stop();
                onResult(value);

                return;
            }
        }

        window.setTimeout(scanFrame, 200);
    };

    scanFrame();

    return { stop };
}
