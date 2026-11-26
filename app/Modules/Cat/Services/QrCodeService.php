<?php

namespace App\Modules\Cat\Services;

use App\Modules\Cat\Models\Cat;
use App\Modules\FeedingSpot\Services\FeedingSpotQrCodeService;
use Illuminate\Support\Str;

class QrCodeService
{
    public function __construct(
        private readonly FeedingSpotQrCodeService $matrixEncoder,
    ) {}

    /**
     * Generate 32-character random token for a cat collar.
     * Raw QR string in the collar tag is: MTP1.C.<token>
     */
    public function generateToken(): string
    {
        return Str::random(32);
    }

    /**
     * Format full QR payload string (PRD Bagian 2 & 4.2: MTP1.C.<token>).
     */
    public function formatQrPayload(string $token): string
    {
        $clean = trim($token);
        if (str_starts_with($clean, 'MTP1.C.')) {
            return $clean;
        }

        return 'MTP1.C.' . $clean;
    }

    /**
     * Parse raw QR content. Returns the token if valid, null otherwise.
     */
    public function parseCatQr(string $qrContent): ?string
    {
        $parts = explode('.', trim($qrContent));
        if (count($parts) === 3 && $parts[0] === 'MTP1' && $parts[1] === 'C' && $parts[2] !== '') {
            return $parts[2];
        }

        return null;
    }

    /**
     * Render raw QR vector SVG (ISO/IEC 18004) for cat collar payload.
     */
    public function renderQrSvg(
        string $payload,
        int $sizePx = 220,
        int $marginModules = 2,
        string $darkColor = '#1E1B18',
        string $lightColor = '#FFFFFF'
    ): string {
        return $this->matrixEncoder->renderSvg(
            $this->formatQrPayload($payload),
            $sizePx,
            $marginModules,
            $darkColor,
            $lightColor
        );
    }

    /**
     * Render raw QR PNG binary (ISO/IEC 18004) for direct download.
     */
    public function renderQrPng(string $payload, int $scale = 10, int $marginModules = 4): string
    {
        return $this->matrixEncoder->renderPng(
            $this->formatQrPayload($payload),
            $scale,
            $marginModules
        );
    }

    /**
     * Build a print-ready / laser-cut-ready SVG for the FRONT face of the
     * Meowtopia cat-head acrylic collar tag (42mm x 44mm proportion).
     */
    public function renderAcrylicFrontSvg(Cat $cat, string $token, bool $showCutline = true): string
    {
        $payload = $this->formatQrPayload($token);
        $matrix = $this->matrixEncoder->generateMatrix($payload);
        $moduleCount = count($matrix);
        $qrPath = $this->buildQrPathData($matrix);

        // Place QR cleanly inside the center of the cat head (viewBox 400x430)
        $qrBoxSize = 148.0;
        $qrX = 126.0;
        $qrY = 178.0;
        $qrScale = $qrBoxSize / $moduleCount;

        $displayName = htmlspecialchars(mb_strimwidth($cat->name, 0, 14, '…'), ENT_QUOTES, 'UTF-8');
        $uniqueCode = htmlspecialchars($cat->unique_code, ENT_QUOTES, 'UTF-8');
        $escapedPayload = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

        $cutline = $showCutline
            ? '<path d="' . self::ACRYLIC_OUTER_CUT_PATH . '" fill="none" stroke="#94A3B8" stroke-width="2" stroke-dasharray="6 4"/>'
            : '';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 430" width="400" height="430" role="img" aria-label="Tag Akrilik Depan {$displayName} ({$escapedPayload})">
  <defs>
    <clipPath id="catTagClipFront-{$uniqueCode}">
      <path d="{$this->innerBodyPath()}"/>
    </clipPath>
    <linearGradient id="calicoOrangeFront-{$uniqueCode}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FB923C"/>
      <stop offset="100%" stop-color="#EA580C"/>
    </linearGradient>
  </defs>

  <!-- Outer clear acrylic contour & keyring tab -->
  <path d="{$this->outerContourPath()}" fill="#FFFFFF" fill-opacity="0.92" stroke="#E2E8F0" stroke-width="3"/>
  {$cutline}

  <!-- Keyring punch hole (top center) -->
  <circle cx="200" cy="46" r="16" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="2.5"/>

  <!-- Printable acrylic inner face -->
  <g clip-path="url(#catTagClipFront-{$uniqueCode})">
    <rect x="0" y="0" width="400" height="430" fill="#FFFBF5"/>

    <!-- Calico orange top-left & bottom accents -->
    <path d="M22,68 C95,62 152,98 158,136 C120,165 78,195 22,202 Z" fill="url(#calicoOrangeFront-{$uniqueCode})"/>
    <path d="M28,286 C86,348 150,378 212,380 C168,408 82,406 24,352 Z" fill="url(#calicoOrangeFront-{$uniqueCode})"/>
    <path d="M378,318 C344,356 302,380 264,386 C318,402 366,384 384,344 Z" fill="#FB923C"/>

    <!-- Inner ears (white rim + soft peach triangle) -->
    <path d="M58,154 L76,92 L134,126 Z" fill="#FFFFFF"/>
    <path d="M72,146 L84,106 L122,128 Z" fill="#FDBA9A"/>
    <path d="M342,154 L324,92 L266,126 Z" fill="#FFFFFF"/>
    <path d="M328,146 L316,106 L278,128 Z" fill="#FDBA9A"/>

    <!-- Whiskers left & right -->
    <g stroke="#5C341E" stroke-width="5.5" stroke-linecap="round">
      <line x1="56" y1="226" x2="96" y2="235"/>
      <line x1="54" y1="262" x2="96" y2="254"/>
      <line x1="344" y1="226" x2="304" y2="235"/>
      <line x1="346" y1="262" x2="304" y2="254"/>
    </g>

    <!-- Cat Name -->
    <text x="200" y="156" text-anchor="middle" font-family="Inter, 'Segoe UI', Arial, sans-serif" font-size="38" font-weight="800" fill="#5C341E" letter-spacing="-0.8">{$displayName}</text>

    <!-- Crisp white quiet-zone plate + QR Code -->
    <rect x="116" y="168" width="168" height="168" rx="10" fill="#FFFFFF"/>
    <g transform="translate({$qrX}, {$qrY}) scale({$qrScale})" shape-rendering="crispEdges">
      <path d="{$qrPath}" fill="#1E1B18"/>
    </g>

    <!-- Manual backup code subtly under QR -->
    <text x="200" y="358" text-anchor="middle" font-family="'JetBrains Mono', Consolas, monospace" font-size="14" font-weight="700" fill="#7C4A2D" letter-spacing="1.2">{$uniqueCode}</text>
  </g>

  <!-- Subtle inner border -->
  <path d="{$this->innerBodyPath()}" fill="none" stroke="#F1E4D8" stroke-width="2"/>
</svg>
SVG;
    }

    /**
     * Build a print-ready / laser-cut-ready SVG for the BACK face of the
     * Meowtopia cat-head acrylic collar tag.
     */
    public function renderAcrylicBackSvg(?string $logoDataUri = null, bool $showCutline = true): string
    {
        $cutline = $showCutline
            ? '<path d="' . self::ACRYLIC_OUTER_CUT_PATH . '" fill="none" stroke="#94A3B8" stroke-width="2" stroke-dasharray="6 4"/>'
            : '';

        $logoBlock = $logoDataUri
            ? '<image href="' . htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8') . '" x="150" y="114" width="100" height="100" clip-path="url(#meowLogoRounded)" preserveAspectRatio="xMidYMid slice"/>'
            : '<rect x="150" y="114" width="100" height="100" rx="22" fill="#EA580C"/><circle cx="200" cy="166" r="28" fill="#FFFBF5"/><path d="M174,154 L178,132 L194,146 Z M226,154 L222,132 L206,146 Z" fill="#FFFBF5"/><circle cx="190" cy="164" r="3.5" fill="#5C341E"/><circle cx="210" cy="164" r="3.5" fill="#5C341E"/><path d="M194,173 Q200,179 206,173" fill="none" stroke="#5C341E" stroke-width="3" stroke-linecap="round"/>';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 430" width="400" height="430" role="img" aria-label="Tag Akrilik Belakang Meowtopia">
  <defs>
    <clipPath id="catTagClipBack">
      <path d="{$this->innerBodyPath()}"/>
    </clipPath>
    <clipPath id="meowLogoRounded">
      <rect x="150" y="114" width="100" height="100" rx="22"/>
    </clipPath>
    <linearGradient id="calicoOrangeBack" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FB923C"/>
      <stop offset="100%" stop-color="#EA580C"/>
    </linearGradient>
  </defs>

  <!-- Outer clear acrylic contour & keyring tab -->
  <path d="{$this->outerContourPath()}" fill="#FFFFFF" fill-opacity="0.92" stroke="#E2E8F0" stroke-width="3"/>
  {$cutline}

  <!-- Keyring punch hole -->
  <circle cx="200" cy="46" r="16" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="2.5"/>

  <g clip-path="url(#catTagClipBack)">
    <rect x="0" y="0" width="400" height="430" fill="#FFFBF5"/>

    <!-- Calico accents matching front -->
    <path d="M22,68 C95,62 152,98 158,136 C120,165 78,195 22,202 Z" fill="url(#calicoOrangeBack)"/>
    <path d="M28,286 C86,348 150,378 212,380 C168,408 82,406 24,352 Z" fill="url(#calicoOrangeBack)"/>
    <path d="M378,318 C344,356 302,380 264,386 C318,402 366,384 384,344 Z" fill="#FB923C"/>

    <!-- Inner ears -->
    <path d="M58,154 L76,92 L134,126 Z" fill="#FFFFFF"/>
    <path d="M72,146 L84,106 L122,128 Z" fill="#FDBA9A"/>
    <path d="M342,154 L324,92 L266,126 Z" fill="#FFFFFF"/>
    <path d="M328,146 L316,106 L278,128 Z" fill="#FDBA9A"/>

    <!-- App Logo -->
    {$logoBlock}

    <!-- Brand Wordmark -->
    <text x="200" y="252" text-anchor="middle" font-family="Inter, 'Segoe UI', Arial, sans-serif" font-size="30" font-weight="800" fill="#5C341E" letter-spacing="-0.5">Meowtopia</text>

    <!-- Store Badges Row -->
    <g transform="translate(68, 274)">
      <rect x="0" y="0" width="124" height="36" rx="8" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1.2"/>
      <polygon points="14,10 14,26 26,18" fill="#16A34A"/>
      <text x="34" y="14" font-family="Inter, Arial, sans-serif" font-size="7.5" font-weight="600" fill="#64748B">GET IT ON</text>
      <text x="34" y="26" font-family="Inter, Arial, sans-serif" font-size="11.5" font-weight="700" fill="#0F172A">Google Play</text>

      <rect x="140" y="0" width="124" height="36" rx="8" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1.2"/>
      <circle cx="158" cy="19" r="7" fill="#0F172A"/>
      <text x="172" y="14" font-family="Inter, Arial, sans-serif" font-size="7" font-weight="600" fill="#64748B">Download on the</text>
      <text x="172" y="26" font-family="Inter, Arial, sans-serif" font-size="11.5" font-weight="700" fill="#0F172A">App Store</text>
    </g>

    <text x="200" y="340" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="11" font-weight="600" fill="#9A6B4C">Pindai QR di sisi depan dengan aplikasi</text>
  </g>

  <path d="{$this->innerBodyPath()}" fill="none" stroke="#F1E4D8" stroke-width="2"/>
</svg>
SVG;
    }

    private const ACRYLIC_OUTER_CUT_PATH = 'M164,66 C164,34 176,16 200,16 C224,16 236,34 236,66 C268,68 292,50 318,52 C346,54 360,84 358,136 C380,176 388,224 380,272 C368,352 296,408 200,408 C104,408 32,352 20,272 C12,224 20,176 42,136 C40,84 54,54 82,52 C108,50 132,68 164,66 Z';

    private function outerContourPath(): string
    {
        return self::ACRYLIC_OUTER_CUT_PATH;
    }

    private function innerBodyPath(): string
    {
        return 'M162,84 C186,78 214,78 238,84 C270,84 294,64 316,66 C336,68 346,94 342,144 C364,180 370,224 364,268 C352,338 286,390 200,390 C114,390 48,338 36,268 C30,224 36,180 58,144 C54,94 64,68 84,66 C106,64 130,84 162,84 Z';
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private function buildQrPathData(array $matrix): string
    {
        $n = count($matrix);
        $paths = [];
        for ($r = 0; $r < $n; $r++) {
            $runStart = null;
            for ($c = 0; $c <= $n; $c++) {
                $dark = ($c < $n) && $matrix[$r][$c];
                if ($dark && $runStart === null) {
                    $runStart = $c;
                } elseif (!$dark && $runStart !== null) {
                    $w = $c - $runStart;
                    $paths[] = "M{$runStart},{$r}h{$w}v1h-{$w}z";
                    $runStart = null;
                }
            }
        }

        return implode('', $paths);
    }
}
