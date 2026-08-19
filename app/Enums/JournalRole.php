<?php

namespace Alpha\Journal\Enums;

/**
 * Per-journal roles (CLAUDE.md §4). Site Admin is deliberately excluded — it's the one global
 * role and lives on users.is_site_admin, not this table.
 */
enum JournalRole: string
{
    case JournalManager = 'journal_manager';
    case Editor = 'editor';
    case SectionEditor = 'section_editor';
    case GuestEditor = 'guest_editor';
    case Reviewer = 'reviewer';
    case Author = 'author';
    case Copyeditor = 'copyeditor';
    case LayoutEditor = 'layout_editor';
    case Proofreader = 'proofreader';
    case Auditor = 'auditor';
    case Reader = 'reader';

    public function label(): string
    {
        return match ($this) {
            self::JournalManager => 'Journal Manager',
            self::Editor => 'Editor',
            self::SectionEditor => 'Section Editor',
            self::GuestEditor => 'Guest Editor',
            self::Reviewer => 'Reviewer',
            self::Author => 'Author',
            self::Copyeditor => 'Copyeditor',
            self::LayoutEditor => 'Layout Editor',
            self::Proofreader => 'Proofreader',
            self::Auditor => 'Auditor',
            self::Reader => 'Reader',
        };
    }

    /**
     * Read-only, journal-wide, no write path anywhere (CLAUDE.md §4.2).
     */
    public function isReadOnly(): bool
    {
        return $this === self::Auditor;
    }
}
