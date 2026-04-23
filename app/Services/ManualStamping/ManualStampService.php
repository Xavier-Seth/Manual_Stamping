<?php

namespace App\Services\ManualStamping;

use setasign\Fpdi\TcpdfFpdi;

class ManualStampService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function stampMasterCopy(string $inputPath, string $outputPath, ?array $preset = null): void
    {
        $preset = $this->normalizePreset($preset);

        $this->stampDocument(
            $inputPath,
            $outputPath,
            function (TcpdfFpdi $pdf, float $pageWidth, float $pageHeight, int $pageNo, int $pageCount) use ($preset): void {
                if ($preset === null) {
                    $this->drawMasterStamp($pdf, $pageWidth, $pageHeight);
                    return;
                }

                $this->applyStampsForPage($pdf, $pageNo, $pageCount, $preset['stamps']);
                $this->applyESignIfNeeded($pdf, $pageNo, $pageCount, $preset['esign']);
            }
        );
    }

    public function stampControlledCopy(string $inputPath, string $outputPath, ?array $preset = null): void
    {
        $preset = $this->normalizePreset($preset);

        $this->stampDocument(
            $inputPath,
            $outputPath,
            function (TcpdfFpdi $pdf, float $pageWidth, float $pageHeight, int $pageNo, int $pageCount) use ($preset): void {
                // FIX: ghost stamps (black "MASTER COPY" background) are part of
                // the legacy layout only. When a preset is active the user has
                // explicitly configured every stamp they want, so we must NOT
                // add the legacy ghosts on top — that was causing the doubles.
                if ($preset === null) {
                    $this->drawControlledCopyMasterStamp($pdf, $pageWidth, $pageHeight);
                    $this->drawControlledCopyStamp($pdf, $pageWidth, $pageHeight);
                    return;
                }

                $this->applyStampsForPage($pdf, $pageNo, $pageCount, $preset['stamps']);
                $this->applyESignIfNeeded($pdf, $pageNo, $pageCount, $preset['esign']);
            }
        );
    }

    public function stampUncontrolledCopy(string $inputPath, string $outputPath, ?array $preset = null): void
    {
        $preset = $this->normalizePreset($preset);

        $this->stampDocument(
            $inputPath,
            $outputPath,
            function (TcpdfFpdi $pdf, float $pageWidth, float $pageHeight, int $pageNo, int $pageCount) use ($preset): void {
                // FIX: same as stampControlledCopy — ghost stamps belong to the
                // legacy layout only. Skip them entirely when a preset is active.
                if ($preset === null) {
                    $this->drawUncontrolledCopyMasterStamp($pdf, $pageWidth, $pageHeight);
                    $this->drawUncontrolledCopyControlledStamp($pdf, $pageWidth, $pageHeight);
                    $this->drawUncontrolledCopyStamp($pdf, $pageWidth, $pageHeight);
                    return;
                }

                $this->applyStampsForPage($pdf, $pageNo, $pageCount, $preset['stamps']);
                $this->applyESignIfNeeded($pdf, $pageNo, $pageCount, $preset['esign']);
            }
        );
    }

    // -------------------------------------------------------------------------
    // Core stamp loop
    // -------------------------------------------------------------------------

    /**
     * Iterate every stamp definition in the preset and draw the ones that
     * match the current page according to their individual page_rule.
     */
    private function applyStampsForPage(
        TcpdfFpdi $pdf,
        int $pageNo,
        int $pageCount,
        array $stamps
    ): void {
        foreach ($stamps as $stamp) {
            if (
                !$this->shouldApplyToPage(
                    $pageNo,
                    $pageCount,
                    $stamp['page_rule'] ?? 'all',
                    $stamp['page_number'] ?? null
                )
            ) {
                continue;
            }

            $color = $stamp['type'] === 'black' ? [0, 0, 0] : [220, 38, 38];

            $this->drawStampAt(
                $pdf,
                (float) $stamp['x'],
                (float) $stamp['y'],
                (float) $stamp['width'],
                (float) $stamp['height'],
                [
                    'line_1' => $stamp['label'],
                    'line_2' => $stamp['sub_label'] ?? '',
                    'color' => $color,
                    'font_family' => 'helvetica',
                    'font_style' => '',
                    'font_size' => 10.5,
                    'line_1_font_size' => 9.0,
                    'line_2_font_size' => 10.5,
                    'text_offset_x' => 0.0,
                    'block_offset_y' => -1.4,
                    'line_gap' => 4.6,
                    'line_2_offset_x' => 0.0,
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Document iteration
    // -------------------------------------------------------------------------

    private function stampDocument(string $inputPath, string $outputPath, callable $stampPage): void
    {
        $pdf = new TcpdfFpdi();

        $pageCount = $pdf->setSourceFile($inputPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            [$pageWidth, $pageHeight] = $this->importSourcePage($pdf, $pageNo);

            $stampPage($pdf, $pageWidth, $pageHeight, $pageNo, $pageCount);
        }

        $pdf->Output($outputPath, 'F');
    }

    private function importSourcePage(TcpdfFpdi $pdf, int $pageNo): array
    {
        $templateId = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($templateId);

        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        return [$size['width'], $size['height']];
    }

    // -------------------------------------------------------------------------
    // Preset normalization
    // -------------------------------------------------------------------------

    private function normalizePreset(?array $preset): ?array
    {
        if ($preset === null) {
            return null;
        }

        // Normalize stamps array
        $rawStamps = $preset['stamps'] ?? [];

        // The model casts 'stamps' to array already, but when passed through
        // ->only() it may still be a plain array of arrays or a JSON string.
        if (is_string($rawStamps)) {
            $rawStamps = json_decode($rawStamps, true) ?? [];
        }

        $stamps = array_map(fn(array $s) => [
            'label' => (string) ($s['label'] ?? 'STAMP'),
            'sub_label' => (string) ($s['sub_label'] ?? ''),
            'type' => in_array($s['type'] ?? '', ['black', 'red']) ? $s['type'] : 'red',
            'x' => (float) ($s['x'] ?? 0),
            'y' => (float) ($s['y'] ?? 0),
            'width' => (float) ($s['width'] ?? 34),
            'height' => (float) ($s['height'] ?? 16),
            'page_rule' => (string) ($s['page_rule'] ?? 'all'),
            'page_number' => isset($s['page_number']) ? (int) $s['page_number'] : null,
        ], $rawStamps);

        // Normalize esign object
        $rawEsign = $preset['esign'] ?? null;

        if (is_string($rawEsign)) {
            $rawEsign = json_decode($rawEsign, true);
        }

        $esign = null;
        if (!empty($rawEsign['enabled'])) {
            $esign = [
                'enabled' => true,
                'x' => isset($rawEsign['x']) ? (float) $rawEsign['x'] : null,
                'y' => isset($rawEsign['y']) ? (float) $rawEsign['y'] : null,
                'width' => isset($rawEsign['width']) ? (float) $rawEsign['width'] : 30.0,
                'height' => isset($rawEsign['height']) ? (float) $rawEsign['height'] : 10.0,
                'page_rule' => (string) ($rawEsign['page_rule'] ?? 'last'),
                'page_number' => isset($rawEsign['page_number']) ? (int) $rawEsign['page_number'] : null,
            ];
        }

        return [
            'stamps' => $stamps,
            'esign' => $esign,
        ];
    }

    // -------------------------------------------------------------------------
    // Page rule helper
    // -------------------------------------------------------------------------

    private function shouldApplyToPage(
        int $pageNo,
        int $pageCount,
        ?string $rule,
        ?int $specificPage = null
    ): bool {
        return match ($rule) {
            'first' => $pageNo === 1,
            'last' => $pageNo === $pageCount,
            'specific' => $specificPage !== null && $pageNo === $specificPage,
            default => true, // 'all', null, ''
        };
    }

    // -------------------------------------------------------------------------
    // E-Sign overlay
    // -------------------------------------------------------------------------

    private function applyESignIfNeeded(
        TcpdfFpdi $pdf,
        int $pageNo,
        int $pageCount,
        ?array $esign
    ): void {
        if ($esign === null || !($esign['enabled'] ?? false)) {
            return;
        }

        if (
            !$this->shouldApplyToPage(
                $pageNo,
                $pageCount,
                $esign['page_rule'] ?? 'last',
                $esign['page_number'] ?? null
            )
        ) {
            return;
        }

        $x = $esign['x'];
        $y = $esign['y'];
        $w = $esign['width'] ?? 30;
        $h = $esign['height'] ?? 10;

        if ($x === null || $y === null) {
            return;
        }

        $pdf->SetDrawColor(31, 41, 55);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $w, $h);

        $pdf->SetFont('helvetica', 'I', 9);

        $text = 'E-SIGN';
        $textWidth = $pdf->GetStringWidth($text);
        $textX = $x + max(1.5, ($w - $textWidth) / 2);
        $textY = $y + max(2.0, ($h / 2));

        $pdf->Text($textX, $textY, $text);
    }

    // -------------------------------------------------------------------------
    // Low-level draw primitives
    // -------------------------------------------------------------------------

    private function drawStampAt(
        TcpdfFpdi $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        array $config
    ): void {
        // Skip drawing if both lines are empty
        if (($config['line_1'] ?? '') === '' && ($config['line_2'] ?? '') === '') {
            return;
        }

        $this->drawTwoLineStamp($pdf, $x, $y, $width, $height, $config);
    }

    private function drawTwoLineStamp(
        TcpdfFpdi $pdf,
        float $stampX,
        float $stampY,
        float $stampWidth,
        float $stampHeight,
        array $config
    ): void {
        [$red, $green, $blue] = $config['color'];

        $pdf->SetDrawColor($red, $green, $blue);
        $pdf->SetTextColor($red, $green, $blue);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($stampX, $stampY, $stampWidth, $stampHeight);

        $fontFamily = $config['font_family'];
        $fontStyle = $config['font_style'];
        $line1FontSize = $config['line_1_font_size'] ?? $config['font_size'];
        $line2FontSize = $config['line_2_font_size'] ?? $config['font_size'];

        $line1 = $config['line_1'];
        $line2 = $config['line_2'];

        $pdf->SetFont($fontFamily, $fontStyle, $line1FontSize);
        $line1Width = $pdf->GetStringWidth($line1);

        $line2Width = 0.0;
        if ($line2 !== '') {
            $pdf->SetFont($fontFamily, $fontStyle, $line2FontSize);
            $line2Width = $pdf->GetStringWidth($line2);
        }

        $textCenterX = $stampX + ($stampWidth / 2);
        $textOffsetX = $config['text_offset_x'];
        $blockOffsetY = $config['block_offset_y'];
        $lineGap = $config['line_gap'];
        $line2OffsetX = $config['line_2_offset_x'];

        // If no sub-label, centre the single line vertically
        $effectiveLineGap = $line2 !== '' ? $lineGap : 0.0;
        $startY = $stampY + ($stampHeight / 2) - ($effectiveLineGap / 2) + $blockOffsetY;

        $pdf->SetFont($fontFamily, $fontStyle, $line1FontSize);
        $pdf->Text(
            $textCenterX - ($line1Width / 2) + $textOffsetX,
            $startY,
            $line1
        );

        if ($line2 !== '') {
            $pdf->SetFont($fontFamily, $fontStyle, $line2FontSize);
            $pdf->Text(
                $textCenterX - ($line2Width / 2) + $textOffsetX + $line2OffsetX,
                $startY + $lineGap,
                $line2
            );
        }
    }

    // -------------------------------------------------------------------------
    // Legacy default-layout draw methods (used when no preset is selected)
    // -------------------------------------------------------------------------

    private function drawMasterStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $layout = $this->masterCopyLayout();
        [$stampX, $stampY] = $this->masterStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp($pdf, $stampX, $stampY, $layout['stamp_w'], $layout['stamp_h'], [
            'line_1' => 'MASTER COPY',
            'line_2' => 'LNU',
            'color' => [220, 38, 38],
            'font_family' => $layout['font_family'],
            'font_style' => $layout['font_style'],
            'font_size' => $layout['font_size'],
            'text_offset_x' => 0.0,
            'block_offset_y' => -2.0,
            'line_gap' => 5.0,
            'line_2_offset_x' => 0.0,
        ]);
    }

    private function drawControlledCopyMasterStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawMasterStampVariant($pdf, $pageWidth, $pageHeight, [0, 0, 0]);
    }

    private function drawControlledCopyStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawControlledStampVariant($pdf, $pageWidth, $pageHeight, [220, 38, 38]);
    }

    private function drawUncontrolledCopyMasterStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawMasterStampVariant($pdf, $pageWidth, $pageHeight, [0, 0, 0]);
    }

    private function drawUncontrolledCopyControlledStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawControlledStampVariant($pdf, $pageWidth, $pageHeight, [0, 0, 0]);
    }

    private function drawUncontrolledCopyStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $layout = $this->uncontrolledCopyLayout();
        [$stampX, $stampY] = $this->uncontrolledStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp($pdf, $stampX, $stampY, $layout['stamp_w'], $layout['stamp_h'], [
            'line_1' => 'UNCONTROLLED COPY',
            'line_2' => 'LNU',
            'color' => [220, 38, 38],
            'font_family' => $layout['font_family'],
            'font_style' => $layout['font_style'],
            'font_size' => $layout['font_size'],
            'line_1_font_size' => $layout['line_1_font_size'],
            'line_2_font_size' => $layout['line_2_font_size'],
            'text_offset_x' => $layout['text_offset_x'],
            'block_offset_y' => $layout['block_offset_y'],
            'line_gap' => $layout['line_gap'],
            'line_2_offset_x' => $layout['line_2_offset_x'],
        ]);
    }

    private function drawMasterStampVariant(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight, array $color): void
    {
        $layout = $this->masterCopyLayout();
        [$stampX, $stampY] = $this->masterStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp($pdf, $stampX, $stampY, $layout['stamp_w'], $layout['stamp_h'], [
            'line_1' => 'MASTER COPY',
            'line_2' => 'LNU',
            'color' => $color,
            'font_family' => $layout['font_family'],
            'font_style' => $layout['font_style'],
            'font_size' => $layout['font_size'],
            'text_offset_x' => 0.0,
            'block_offset_y' => -2.0,
            'line_gap' => 5.0,
            'line_2_offset_x' => 0.0,
        ]);
    }

    private function drawControlledStampVariant(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight, array $color): void
    {
        $layout = $this->controlledCopyLayout();
        [$stampX, $stampY] = $this->controlledStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp($pdf, $stampX, $stampY, $layout['stamp_w'], $layout['stamp_h'], [
            'line_1' => 'CONTROLLED COPY',
            'line_2' => 'LNU',
            'color' => $color,
            'font_family' => $layout['font_family'],
            'font_style' => $layout['font_style'],
            'font_size' => $layout['font_size'],
            'line_1_font_size' => $layout['line_1_font_size'],
            'line_2_font_size' => $layout['line_2_font_size'],
            'text_offset_x' => $layout['text_offset_x'],
            'block_offset_y' => $layout['block_offset_y'],
            'line_gap' => $layout['line_gap'],
            'line_2_offset_x' => $layout['line_2_offset_x'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Legacy coordinate helpers
    // -------------------------------------------------------------------------

    private function masterStampCoordinates(float $pageWidth, float $pageHeight): array
    {
        $layout = $this->masterCopyLayout();
        $boxX = $pageWidth - $layout['box_right_offset'] - $layout['box_w'];
        $boxY = $pageHeight - $layout['box_bottom_offset'] - $layout['box_h'];

        return [
            $boxX + (($layout['box_w'] - $layout['stamp_w']) / 2) + $layout['stamp_offset_x'],
            $boxY + (($layout['box_h'] - $layout['stamp_h']) / 2) + $layout['stamp_offset_y'],
        ];
    }

    private function controlledStampCoordinates(float $pageWidth, float $pageHeight): array
    {
        $masterLayout = $this->masterCopyLayout();
        $controlledLayout = $this->controlledCopyLayout();
        [$masterX, $masterY] = $this->masterStampCoordinates($pageWidth, $pageHeight);

        return [
            $masterX + $masterLayout['stamp_w'] + $controlledLayout['gap_from_master'] + $controlledLayout['stamp_offset_x'],
            $masterY + (($masterLayout['stamp_h'] - $controlledLayout['stamp_h']) / 2) + $controlledLayout['stamp_offset_y'],
        ];
    }

    private function uncontrolledStampCoordinates(float $pageWidth, float $pageHeight): array
    {
        $masterLayout = $this->masterCopyLayout();
        $controlledLayout = $this->controlledCopyLayout();
        $uncontrolledLayout = $this->uncontrolledCopyLayout();

        [$masterX, $masterY] = $this->masterStampCoordinates($pageWidth, $pageHeight);
        [$controlledX, $controlledY] = $this->controlledStampCoordinates($pageWidth, $pageHeight);

        $pairLeft = min($masterX, $controlledX);
        $pairRight = max(
            $masterX + $masterLayout['stamp_w'],
            $controlledX + $controlledLayout['stamp_w']
        );
        $pairWidth = $pairRight - $pairLeft;

        return [
            $pairLeft + (($pairWidth - $uncontrolledLayout['stamp_w']) / 2) + $uncontrolledLayout['stamp_offset_x'],
            min($masterY, $controlledY)
            - $uncontrolledLayout['stamp_h']
            - $uncontrolledLayout['gap_above_pair']
            + $uncontrolledLayout['stamp_offset_y'],
        ];
    }

    // -------------------------------------------------------------------------
    // Layout definitions (unchanged from original)
    // -------------------------------------------------------------------------

    private function masterCopyLayout(): array
    {
        return [
            'box_w' => 22.0,
            'box_h' => 42.0,
            'box_right_offset' => 75.0,
            'box_bottom_offset' => 11.5,
            'stamp_w' => 34.0,
            'stamp_h' => 16.0,
            'stamp_offset_x' => -1.5,
            'stamp_offset_y' => 1.5,
            'font_family' => 'helvetica',
            'font_style' => '',
            'font_size' => 12,
        ];
    }

    private function controlledCopyLayout(): array
    {
        return [
            'gap_from_master' => 4.2,
            'stamp_w' => 34.0,
            'stamp_h' => 16.0,
            'stamp_offset_x' => 0.0,
            'stamp_offset_y' => 0.0,
            'font_family' => 'helvetica',
            'font_style' => '',
            'font_size' => 10.5,
            'line_1_font_size' => 9.0,
            'line_2_font_size' => 10.5,
            'text_offset_x' => 0.0,
            'block_offset_y' => -1.4,
            'line_gap' => 4.6,
            'line_2_offset_x' => 0.0,
        ];
    }

    private function uncontrolledCopyLayout(): array
    {
        return [
            'gap_above_pair' => 2.0,
            'stamp_w' => 39.0,
            'stamp_h' => 16.0,
            'stamp_offset_x' => 0.0,
            'stamp_offset_y' => 0.0,
            'font_family' => 'helvetica',
            'font_style' => '',
            'font_size' => 10.5,
            'line_1_font_size' => 9.0,
            'line_2_font_size' => 10.5,
            'text_offset_x' => 0.0,
            'block_offset_y' => -1.4,
            'line_gap' => 4.6,
            'line_2_offset_x' => 0.0,
        ];
    }
}