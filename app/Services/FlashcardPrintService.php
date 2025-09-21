<?php

namespace App\Services;

use App\Models\Flashcard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class FlashcardPrintService
{
    /**
     * Available print layouts
     */
    public const LAYOUTS = [
        'index' => 'Traditional Index Cards (3x5)',
        'grid' => 'Grid Layout (6 per page)',
        'foldable' => 'Foldable Cards (2 per page)',
        'study_sheet' => 'Study Sheet (List format)',
    ];

    /**
     * Page size options
     */
    public const PAGE_SIZES = [
        'letter' => 'US Letter (8.5" x 11")',
        'a4' => 'A4 (210mm x 297mm)',
        'legal' => 'US Legal (8.5" x 14")',
        'index35' => 'Index 3x5 (3" x 5")',
        'index46' => 'Index 4x6 (4" x 6")',
    ];

    /**
     * Generate PDF for flashcards
     *
     * @param  EloquentCollection|array  $flashcards
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePDF($flashcards, string $layout, array $options = [])
    {
        try {
            // Memory optimization: check collection size and warn if too large
            $cardCount = is_array($flashcards) ? count($flashcards) : $flashcards->count();
            if ($cardCount > 1000) {
                Log::warning('Large flashcard PDF generation requested', [
                    'card_count' => $cardCount,
                    'memory_before' => memory_get_usage(true) / 1024 / 1024 .'MB',
                ]);
            }
            // Default options
            $options = array_merge([
                'page_size' => 'letter',
                'orientation' => $this->getDefaultOrientation($layout),
                'color_mode' => 'color', // 'color' or 'grayscale'
                'include_answers' => true,
                'include_hints' => true,
                'font_size' => 'medium', // 'small', 'medium', 'large'
                'margin' => 'normal', // 'tight', 'normal', 'wide'
            ], $options);

            // Convert to collection if array
            if (is_array($flashcards)) {
                $flashcards = new EloquentCollection($flashcards);
            } elseif ($flashcards instanceof Collection && ! $flashcards instanceof EloquentCollection) {
                $flashcards = new EloquentCollection($flashcards->all());
            }

            // Validate layout
            if (! array_key_exists($layout, self::LAYOUTS)) {
                throw new \InvalidArgumentException("Invalid layout: {$layout}");
            }

            // Generate HTML content
            $html = $this->generateHTML($flashcards, $layout, $options);

            // Ensure required directories exist
            $this->ensureDirectoriesExist();

            // Configure PDF
            $pdf = Pdf::loadHTML($html);

            // Set paper size and orientation
            $pdf->setPaper($options['page_size'], $options['orientation']);

            // Set options for better rendering and performance
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => false, // Security: disable remote content
                'defaultFont' => 'DejaVu Sans', // Better Unicode support
                'dpi' => $this->getOptimalDpi($flashcards->count()), // Dynamic DPI based on card count
                'defaultPaperOrientation' => $options['orientation'],
                'defaultPaperSize' => $options['page_size'],
                'fontDir' => storage_path('fonts/'),
                'fontCache' => storage_path('fonts/'),
                'tempDir' => storage_path('app/temp/'),
                'chroot' => base_path(),
                'logOutputFile' => storage_path('logs/dompdf.log'),
                'isJavascriptEnabled' => false, // Security: disable JS
                'debugKeepTemp' => false, // Performance: don't keep temp files
                'debugCss' => false, // Performance: disable CSS debugging
                'debugLayout' => false, // Performance: disable layout debugging
                'debugLayoutLines' => false, // Performance: disable layout line debugging
                'debugLayoutBlocks' => false, // Performance: disable layout block debugging
                'debugLayoutInline' => false, // Performance: disable inline debugging
                'debugLayoutPaddingBox' => false, // Performance: disable padding box debugging
            ]);

            return $pdf;

        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'layout' => $layout,
                'options' => $options,
                'flashcard_count' => count($flashcards),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate HTML content for the PDF
     */
    protected function generateHTML(EloquentCollection $flashcards, string $layout, array $options): string
    {
        // Prepare cards data for different layouts
        $cardsData = $this->prepareCardsForLayout($flashcards, $layout, $options);

        // Get print-specific CSS
        $css = $this->generateCSS($layout, $options);

        // Render the appropriate layout view
        $viewName = "flashcards.print.layouts.{$layout}";

        try {
            return View::make('flashcards.print.wrapper', [
                'layout' => $layout,
                'options' => $options,
                'css' => $css,
                'content' => View::make($viewName, [
                    'cards' => $cardsData,
                    'options' => $options,
                    'flashcards' => $flashcards,
                ])->render(),
            ])->render();
        } catch (\Exception $e) {
            // Fallback to simple HTML if view rendering fails
            Log::warning('Failed to render print view, using fallback', [
                'layout' => $layout,
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackHTML($flashcards, $layout, $options, $css);
        }
    }

    /**
     * Prepare flashcard data based on layout requirements
     */
    protected function prepareCardsForLayout(EloquentCollection $flashcards, string $layout, array $options): array
    {
        $cards = [];

        foreach ($flashcards as $flashcard) {
            /** @var \App\Models\Flashcard $flashcard */
            $cardData = [
                'id' => $flashcard->id,
                'question' => $this->sanitizeForPrint($flashcard->question),
                'answer' => $this->sanitizeForPrint($flashcard->answer),
                'hint' => $options['include_hints'] && $flashcard->hint ?
                    $this->sanitizeForPrint($flashcard->hint) : null,
                'card_type' => $flashcard->card_type,
                'difficulty' => $flashcard->difficulty_level,
                'tags' => $flashcard->tags ?? [],
                'choices' => [],
                'correct_choices' => [],
                'cloze_data' => null,
                'image_data' => null,
            ];

            // Process card type specific data
            switch ($flashcard->card_type) {
                case Flashcard::CARD_TYPE_MULTIPLE_CHOICE:
                    $cardData['choices'] = $flashcard->choices ?? [];
                    $cardData['correct_choices'] = $flashcard->correct_choices ?? [];
                    break;

                case Flashcard::CARD_TYPE_TRUE_FALSE:
                    $cardData['choices'] = $flashcard->choices ?? ['True', 'False'];
                    $cardData['correct_choices'] = $flashcard->correct_choices ?? [];
                    break;

                case Flashcard::CARD_TYPE_CLOZE:
                    $cardData['cloze_data'] = [
                        'text' => $this->sanitizeForPrint($flashcard->cloze_text ?? ''),
                        'answers' => $flashcard->cloze_answers ?? [],
                    ];
                    break;

                case Flashcard::CARD_TYPE_IMAGE_OCCLUSION:
                    $cardData['image_data'] = [
                        'question_image_url' => $flashcard->question_image_url,
                        'answer_image_url' => $flashcard->answer_image_url,
                        'occlusion_data' => $flashcard->occlusion_data ?? [],
                    ];
                    break;
            }

            $cards[] = $cardData;
        }

        // Layout-specific grouping
        switch ($layout) {
            case 'grid':
                // Group cards in sets of 6 per page
                return array_chunk($cards, 6);

            case 'foldable':
                // Group cards in pairs for folding
                return array_chunk($cards, 2);

            case 'index':
            case 'study_sheet':
            default:
                return $cards;
        }
    }

    /**
     * Generate layout-specific CSS
     */
    protected function generateCSS(string $layout, array $options): string
    {
        $css = $this->getBasePrintCSS($options);

        switch ($layout) {
            case 'index':
                $css .= $this->getIndexCardCSS($options);
                break;
            case 'grid':
                $css .= $this->getGridLayoutCSS($options);
                break;
            case 'foldable':
                $css .= $this->getFoldableLayoutCSS($options);
                break;
            case 'study_sheet':
                $css .= $this->getStudySheetCSS($options);
                break;
        }

        return $css;
    }

    /**
     * Get base CSS for all print layouts
     */
    protected function getBasePrintCSS(array $options): string
    {
        $fontSize = $this->getFontSize($options['font_size']);
        $margins = $this->getMargins($options['margin']);
        $colorMode = $options['color_mode'] === 'grayscale' ? 'grayscale' : 'color';

        return "
            @media print {
                * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
            }
            
            body {
                font-family: 'DejaVu Sans', Arial, sans-serif;
                font-size: {$fontSize}px;
                line-height: 1.4;
                color: #000;
                background: #fff;
                margin: {$margins['top']}mm {$margins['right']}mm {$margins['bottom']}mm {$margins['left']}mm;
                padding: 0;
            }
            
            .print-wrapper {
                width: 100%;
                height: 100%;
            }
            
            .card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            
            .card-question {
                font-weight: bold;
                margin-bottom: 8px;
            }
            
            .card-answer {
                margin-bottom: 6px;
            }
            
            .card-hint {
                font-style: italic;
                color: #666;
                font-size: 90%;
            }
            
            .card-type-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 75%;
                font-weight: bold;
                margin-bottom: 4px;
                background: #f0f0f0;
                color: #333;
            }
            
            .difficulty-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 75%;
                font-weight: bold;
                margin-left: 4px;
            }
            
            .difficulty-easy { background: #d4edda; color: #155724; }
            .difficulty-medium { background: #fff3cd; color: #856404; }
            .difficulty-hard { background: #f8d7da; color: #721c24; }
            
            .choices-list {
                list-style: none;
                padding-left: 0;
                margin: 6px 0;
            }
            
            .choices-list li {
                margin: 2px 0;
                padding: 2px 0;
            }
            
            .correct-choice {
                font-weight: bold;
                background: #d4edda;
                padding: 2px 4px;
                border-radius: 2px;
            }
            
            .cloze-text {
                line-height: 1.6;
            }
            
            .cloze-blank {
                display: inline-block;
                border-bottom: 1px solid #000;
                min-width: 60px;
                height: 1em;
                margin: 0 2px;
            }
            
            .tags {
                font-size: 80%;
                color: #666;
                margin-top: 4px;
            }
            
            .tag {
                display: inline-block;
                background: #f0f0f0;
                padding: 1px 4px;
                border-radius: 2px;
                margin-right: 4px;
            }
        ";
    }

    /**
     * Get CSS for traditional index cards layout
     */
    protected function getIndexCardCSS(array $options): string
    {
        return "
            .index-card {
                width: 3in;
                height: 5in;
                border: 1px solid #000;
                margin: 0.25in;
                padding: 0.125in;
                float: left;
                box-sizing: border-box;
            }
            
            .index-card-front,
            .index-card-back {
                page-break-inside: avoid;
                height: 48%;
                border-bottom: 1px dashed #ccc;
                padding-bottom: 6px;
                margin-bottom: 6px;
            }
            
            .index-card-back {
                border-bottom: none;
                margin-bottom: 0;
            }
            
            .cut-line {
                border-top: 1px dashed #999;
                margin: 0.125in 0;
                position: relative;
            }
            
            .cut-line::before {
                content: '✂';
                position: absolute;
                left: -15px;
                top: -8px;
                font-size: 12px;
                color: #999;
            }
        ";
    }

    /**
     * Get CSS for grid layout (6 cards per page)
     */
    protected function getGridLayoutCSS(array $options): string
    {
        return '
            .grid-container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-gap: 0.25in;
                page-break-after: always;
            }
            
            .grid-card {
                border: 1px solid #ccc;
                padding: 0.125in;
                margin-bottom: 0.125in;
                min-height: 3.5in;
                box-sizing: border-box;
            }
            
            .grid-card:nth-child(6n) {
                page-break-after: always;
            }
            
            .grid-card-header {
                border-bottom: 1px solid #eee;
                padding-bottom: 4px;
                margin-bottom: 8px;
            }
        ';
    }

    /**
     * Get CSS for foldable cards layout
     */
    protected function getFoldableLayoutCSS(array $options): string
    {
        return "
            .foldable-page {
                page-break-after: always;
                height: 100vh;
            }
            
            .foldable-card {
                width: 100%;
                height: 48%;
                border: 1px solid #000;
                margin-bottom: 4%;
                padding: 0.25in;
                box-sizing: border-box;
                position: relative;
            }
            
            .fold-line {
                position: absolute;
                top: 50%;
                left: 0;
                right: 0;
                height: 1px;
                border-top: 2px dashed #999;
                z-index: 10;
            }
            
            .fold-line::before {
                content: 'FOLD HERE';
                position: absolute;
                right: 10px;
                top: -10px;
                background: white;
                padding: 0 4px;
                font-size: 8px;
                color: #999;
            }
            
            .foldable-front,
            .foldable-back {
                height: 48%;
                padding: 0.125in 0;
            }
            
            .foldable-back {
                transform: rotate(180deg);
                margin-top: 4%;
            }
        ";
    }

    /**
     * Get CSS for study sheet layout
     */
    protected function getStudySheetCSS(array $options): string
    {
        return '
            .study-sheet {
                max-width: 100%;
            }
            
            .study-item {
                margin-bottom: 0.75in;
                padding: 0.125in;
                border-bottom: 1px solid #eee;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            
            .study-item:last-child {
                border-bottom: none;
            }
            
            .study-question {
                font-weight: bold;
                margin-bottom: 8px;
                color: #000;
            }
            
            .study-answer {
                margin-left: 20px;
                margin-bottom: 6px;
            }
            
            .study-meta {
                font-size: 85%;
                color: #666;
                margin-top: 8px;
            }
        ';
    }

    /**
     * Get default orientation for layout
     */
    protected function getDefaultOrientation(string $layout): string
    {
        return match ($layout) {
            'foldable' => 'landscape',
            'grid' => 'portrait',
            'index' => 'landscape',
            'study_sheet' => 'portrait',
            default => 'portrait'
        };
    }

    /**
     * Get font size in pixels
     */
    protected function getFontSize(string $size): int
    {
        return match ($size) {
            'small' => 10,
            'medium' => 12,
            'large' => 14,
            default => 12
        };
    }

    /**
     * Get margins in millimeters
     */
    protected function getMargins(string $margin): array
    {
        return match ($margin) {
            'tight' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            'normal' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
            'wide' => ['top' => 25, 'right' => 25, 'bottom' => 25, 'left' => 25],
            default => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15]
        };
    }

    /**
     * Sanitize content for PDF printing
     */
    protected function sanitizeForPrint(string $content): string
    {
        // Remove or convert HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Escape any remaining HTML
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        // Convert newlines to <br> tags for PDF rendering
        $content = nl2br($content);

        return $content;
    }

    /**
     * Validate print options
     */
    public function validateOptions(array $options): array
    {
        $errors = [];

        if (isset($options['page_size']) && ! array_key_exists($options['page_size'], self::PAGE_SIZES)) {
            $errors[] = 'Invalid page size selected';
        }

        if (isset($options['font_size']) && ! in_array($options['font_size'], ['small', 'medium', 'large'])) {
            $errors[] = 'Invalid font size selected';
        }

        if (isset($options['margin']) && ! in_array($options['margin'], ['tight', 'normal', 'wide'])) {
            $errors[] = 'Invalid margin setting selected';
        }

        if (isset($options['color_mode']) && ! in_array($options['color_mode'], ['color', 'grayscale'])) {
            $errors[] = 'Invalid color mode selected';
        }

        return $errors;
    }

    /**
     * Get available layouts
     */
    public static function getAvailableLayouts(): array
    {
        return self::LAYOUTS;
    }

    /**
     * Get available page sizes
     */
    public static function getAvailablePageSizes(): array
    {
        return self::PAGE_SIZES;
    }

    /**
     * Generate fallback HTML when view rendering fails
     */
    protected function generateFallbackHTML(EloquentCollection $flashcards, string $layout, array $options, string $css): string
    {
        $html = "<!DOCTYPE html><html><head><title>Flashcards Print</title><style>{$css}</style></head><body>";

        foreach ($flashcards as $index => $flashcard) {
            /** @var \App\Models\Flashcard $flashcard */
            $html .= "<div class='card'>";
            $html .= '<h3>Q: '.htmlspecialchars($flashcard->question).'</h3>';
            if ($options['include_answers']) {
                $html .= '<p>A: '.htmlspecialchars($flashcard->answer).'</p>';
            }
            if ($options['include_hints'] && $flashcard->hint) {
                $html .= '<p><em>Hint: '.htmlspecialchars($flashcard->hint).'</em></p>';
            }
            $html .= '</div>';

            // Page break every few cards based on layout
            $cardsPerPage = match ($layout) {
                'grid' => 6,
                'foldable' => 2,
                'study_sheet' => 3,
                default => 4
            };

            if (($index + 1) % $cardsPerPage === 0 && $index < $flashcards->count() - 1) {
                $html .= "<div style='page-break-after: always;'></div>";
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Ensure required directories exist for PDF generation
     */
    protected function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('fonts'),
            storage_path('app/temp'),
            storage_path('logs'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * Get optimal DPI based on flashcard count to balance quality and memory usage
     */
    protected function getOptimalDpi(int $cardCount): int
    {
        // For large collections, use lower DPI to reduce memory usage
        if ($cardCount > 500) {
            return 150; // Lower DPI for large sets
        } elseif ($cardCount > 200) {
            return 200; // Medium DPI for medium sets
        } elseif ($cardCount > 50) {
            return 250; // Higher DPI for smaller sets
        } else {
            return 300; // Maximum DPI for small sets
        }
    }
}
