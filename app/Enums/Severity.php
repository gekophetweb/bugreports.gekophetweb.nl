<?php

namespace App\Enums;

enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Unknown = 'unknown';

    /** CVSS bands: Critical 9.0–10, High 7.0–8.9, Medium 4.0–6.9, Low 0.1–3.9. */
    public static function fromScore(?float $score): self
    {
        return match (true) {
            $score === null => self::Unknown,
            $score >= 9.0 => self::Critical,
            $score >= 7.0 => self::High,
            $score >= 4.0 => self::Medium,
            $score > 0.0 => self::Low,
            default => self::Unknown,
        };
    }

    /** A source's own label (NVD baseSeverity, GHSA "MODERATE", …). */
    public static function fromLabel(?string $label): self
    {
        return match (strtolower(trim((string) $label))) {
            'critical' => self::Critical,
            'high' => self::High,
            'moderate', 'medium' => self::Medium,
            'low' => self::Low,
            default => self::Unknown,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::Unknown => 0,
        };
    }

    /** @return list<self> this severity and every more severe one, most severe first */
    public function andAbove(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->rank() >= $this->rank()));
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
