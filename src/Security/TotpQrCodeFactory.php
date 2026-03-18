<?php

declare(strict_types=1);

namespace App\Security;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class TotpQrCodeFactory
{
    public function asDataUri(string $payload): string
    {
        $writer = new PngWriter();
        $qrCode = new QrCode(
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $writer->write($qrCode)->getDataUri();
    }
}
