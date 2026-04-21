<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class EmailTemplateRenderer
{
    public function render(EmailTemplate $template, array $data = []): array
    {
        $blocks = $this->resolveValue($template->editorBlocks(), $data);

        return [
            'subject' => $this->interpolate((string) $template->subject, $data),
            'html' => $this->renderHtmlBlocks($blocks),
            'text' => trim($this->renderTextBlocks($blocks)),
        ];
    }

    protected function renderHtmlBlocks(array $blocks): string
    {
        return implode('', array_map(fn (array $block) => $this->renderHtmlBlock($block), $blocks));
    }

    protected function renderHtmlBlock(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return match ($block['type'] ?? '') {
            'heading' => $this->renderHeadingHtml($data),
            'text' => $this->renderTextHtml($data),
            'image' => $this->renderImageHtml($data),
            'button' => $this->renderButtonHtml($data),
            'divider' => $this->renderDividerHtml($data),
            'spacer' => $this->renderSpacerHtml($data),
            'columns' => $this->renderColumnsHtml($data),
            default => '',
        };
    }

    protected function renderHeadingHtml(array $data): string
    {
        $level = in_array($data['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4'], true) ? $data['level'] : 'h2';
        $align = ['left' => 'left', 'center' => 'center', 'right' => 'right'][$data['align'] ?? 'left'] ?? 'left';
        $sizes = ['h1' => '32px', 'h2' => '28px', 'h3' => '24px', 'h4' => '20px'];

        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td style="padding:16px 24px 8px 24px; text-align:%s;"><%s style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:%s; line-height:1.3; font-weight:700; color:#111827;">%s</%s></td></tr></table>',
            e($align),
            $level,
            $sizes[$level],
            e((string) ($data['text'] ?? '')),
            $level,
        );
    }

    protected function renderTextHtml(array $data): string
    {
        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td style="padding:8px 24px 16px 24px; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.6; color:#374151;">%s</td></tr></table>',
            nl2br(e((string) ($data['content'] ?? ''))),
        );
    }

    protected function renderImageHtml(array $data): string
    {
        $src = trim((string) ($data['src'] ?? ''));

        if ($src === '') {
            return '';
        }

        $width = ['full' => '100%', 'wide' => '560', 'medium' => '420', 'small' => '280'][$data['width'] ?? 'full'] ?? '100%';
        $imgWidth = $width === '100%' ? '100%' : $width.'px';
        $caption = trim((string) ($data['caption'] ?? ''));

        $html = sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:12px 24px;"><img src="%s" alt="%s" style="display:block; width:%s; max-width:100%%; height:auto; border:0; border-radius:8px;" /></td></tr>',
            e($src),
            e((string) ($data['alt'] ?? '')),
            e($imgWidth),
        );

        if ($caption !== '') {
            $html .= sprintf(
                '<tr><td align="center" style="padding:0 24px 16px 24px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#6b7280;">%s</td></tr>',
                e($caption),
            );
        }

        return $html.'</table>';
    }

    protected function renderButtonHtml(array $data): string
    {
        $url = trim((string) ($data['url'] ?? ''));
        $text = trim((string) ($data['text'] ?? ''));

        if ($url === '' || $text === '') {
            return '';
        }

        $align = ['left' => 'left', 'center' => 'center', 'right' => 'right'][$data['align'] ?? 'center'] ?? 'center';
        $size = [
            'sm' => ['padding' => '10px 16px', 'font' => '14px'],
            'md' => ['padding' => '12px 20px', 'font' => '15px'],
            'lg' => ['padding' => '14px 24px', 'font' => '16px'],
        ][$data['size'] ?? 'md'] ?? ['padding' => '12px 20px', 'font' => '15px'];
        $variant = [
            'primary' => ['background' => '#111827', 'color' => '#ffffff', 'border' => '#111827'],
            'secondary' => ['background' => '#4b5563', 'color' => '#ffffff', 'border' => '#4b5563'],
            'outline' => ['background' => '#ffffff', 'color' => '#111827', 'border' => '#d1d5db'],
            'ghost' => ['background' => 'transparent', 'color' => '#111827', 'border' => 'transparent'],
        ][$data['variant'] ?? 'primary'] ?? ['background' => '#111827', 'color' => '#ffffff', 'border' => '#111827'];

        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td align="%s" style="padding:8px 24px 20px 24px;"><a href="%s"%s style="display:inline-block; padding:%s; border-radius:6px; border:1px solid %s; background:%s; color:%s; font-family:Arial,Helvetica,sans-serif; font-size:%s; font-weight:600; line-height:1; text-decoration:none;">%s</a></td></tr></table>',
            e($align),
            e($url),
            ! empty($data['new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '',
            $size['padding'],
            $variant['border'],
            $variant['background'],
            $variant['color'],
            $size['font'],
            e($text),
        );
    }

    protected function renderDividerHtml(array $data): string
    {
        $spacing = ['sm' => '12px', 'md' => '20px', 'lg' => '32px'][$data['spacing'] ?? 'md'] ?? '20px';
        $style = $data['style'] ?? 'line';

        $line = match ($style) {
            'dots' => '<div style="border-top:2px dotted #d1d5db; line-height:0;">&nbsp;</div>',
            'none' => '',
            default => '<div style="border-top:1px solid #e5e7eb; line-height:0;">&nbsp;</div>',
        };

        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td style="padding:%1$s 24px;">%2$s</td></tr></table>',
            $spacing,
            $line,
        );
    }

    protected function renderSpacerHtml(array $data): string
    {
        $height = ['xs' => '16px', 'sm' => '24px', 'md' => '48px', 'lg' => '80px', 'xl' => '120px'][$data['size'] ?? 'md'] ?? '48px';

        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td style="height:%s; line-height:%s; font-size:0;">&nbsp;</td></tr></table>',
            $height,
            $height,
        );
    }

    protected function renderColumnsHtml(array $data): string
    {
        $count = max(2, min(4, (int) ($data['count'] ?? 2)));
        $gutter = ['sm' => 12, 'md' => 20, 'lg' => 32][$data['gap'] ?? 'md'] ?? 20;
        $cells = '';

        for ($index = 0; $index < $count; $index++) {
            $columnBlocks = is_array($data["col_{$index}"] ?? null) ? $data["col_{$index}"] : [];
            $cells .= sprintf(
                '<td valign="top" width="%1$s%%" style="width:%1$s%%; padding-right:%2$spx;%3$s">%4$s</td>',
                round(100 / $count, 2),
                $index < $count - 1 ? $gutter : 0,
                $index === $count - 1 ? ' padding-right:0;' : '',
                $this->renderHtmlBlocks($columnBlocks),
            );
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:8px 24px 16px 24px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'.$cells.'</tr></table></td></tr></table>';
    }

    protected function renderTextBlocks(array $blocks): string
    {
        return implode("\n", array_filter(array_map(fn (array $block) => $this->renderTextBlock($block), $blocks)))."\n";
    }

    protected function renderTextBlock(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return match ($block['type'] ?? '') {
            'heading' => strtoupper((string) ($data['text'] ?? '')),
            'text' => (string) ($data['content'] ?? ''),
            'image' => trim((string) ($data['caption'] ?? $data['alt'] ?? '')),
            'button' => trim((string) ($data['text'] ?? '')).' - '.trim((string) ($data['url'] ?? '')),
            'divider' => '----------------------------------------',
            'spacer' => '',
            'columns' => $this->renderColumnsText($data),
            default => '',
        };
    }

    protected function renderColumnsText(array $data): string
    {
        $count = max(2, min(4, (int) ($data['count'] ?? 2)));
        $parts = [];

        for ($index = 0; $index < $count; $index++) {
            $columnBlocks = is_array($data["col_{$index}"] ?? null) ? $data["col_{$index}"] : [];
            $columnText = trim($this->renderTextBlocks($columnBlocks));

            if ($columnText !== '') {
                $parts[] = 'Column '.($index + 1).":\n".$columnText;
            }
        }

        return implode("\n\n", $parts);
    }

    protected function resolveValue(mixed $value, array $data): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->resolveValue($item, $data), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return $this->interpolate($value, $data);
    }

    protected function interpolate(string $value, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([^\}]+?)\s*\}\}/', function (array $matches) use ($data) {
            $resolved = Arr::get($data, trim($matches[1]));

            if (is_array($resolved)) {
                return implode(', ', array_map(fn ($item) => $this->stringifyResolvedValue($item), $resolved));
            }

            return $this->stringifyResolvedValue($resolved, $matches[0]);
        }, $value) ?? $value;
    }

    protected function stringifyResolvedValue(mixed $value, string $fallback = ''): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            $value === null => $fallback,
            $value instanceof HtmlString => (string) $value,
            default => (string) $value,
        };
    }
}
