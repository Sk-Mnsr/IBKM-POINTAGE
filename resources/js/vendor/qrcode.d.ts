interface QrCode {
    addData(data: string): void;
    make(): void;
    createSvgTag(options: { cellSize?: number; margin?: number; scalable?: boolean; alt?: string }): string;
}

declare function qrcode(typeNumber: number, errorCorrectionLevel: 'L' | 'M' | 'Q' | 'H'): QrCode;

export default qrcode;
