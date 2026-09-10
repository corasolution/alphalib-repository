import { usePage } from '@inertiajs/react';

export interface LocaleOption {
    code: string;
    short: string;
    name: string;
}

type TranslationTree = { [key: string]: string | TranslationTree };

interface I18nProps {
    locale: string;
    locales: LocaleOption[];
    translations: TranslationTree;
}

function lookup(tree: TranslationTree, key: string): string | undefined {
    const value = key.split('.').reduce<string | TranslationTree | undefined>((node, segment) => {
        if (node === undefined || typeof node === 'string') return undefined;
        return node[segment];
    }, tree);

    return typeof value === 'string' ? value : undefined;
}

/**
 * Resolves Laravel's pluralisation syntax: `{0} none|{1} :count item|[2,*] :count items`.
 *
 * Implemented here rather than sending pre-pluralised strings from PHP because the count is
 * often only known in the component (a filtered list, a client-side tally), and shipping one
 * string per possible count is not a thing.
 */
function choose(line: string, count: number): string {
    const branches = line.split('|');

    for (const branch of branches) {
        const exact = branch.match(/^\s*\{(\d+)\}\s*(.*)$/s);
        if (exact && Number(exact[1]) === count) return exact[2];

        const range = branch.match(/^\s*\[(\d+),\s*(\d+|\*)\]\s*(.*)$/s);
        if (range) {
            const low = Number(range[1]);
            const high = range[2] === '*' ? Infinity : Number(range[2]);
            if (count >= low && count <= high) return range[3];
        }
    }

    // No branch matched — fall back to the last one rather than rendering the raw pipe syntax.
    return branches[branches.length - 1].replace(/^\s*(\{\d+\}|\[\d+,\s*(\d+|\*)\])\s*/, '');
}

/**
 * Public-site translations, from the props shared by HandleInertiaRequests.
 *
 * `t()` returns the key itself when a string is missing. That is deliberate: a visible
 * `journal.archive` on the page is an obvious bug report, whereas an empty string or a thrown
 * error would either hide the problem or take the whole page down over one missing word.
 */
export function useTranslations() {
    const page = usePage<{ locale?: string; locales?: LocaleOption[]; translations?: TranslationTree }>();
    const props = page.props as unknown as I18nProps;

    const translations = props.translations ?? {};

    const t = (key: string, replacements: Record<string, string | number> = {}): string => {
        let line = lookup(translations, key) ?? key;

        if (typeof replacements.count === 'number' && line.includes('|')) {
            line = choose(line, replacements.count);
        }

        for (const [name, value] of Object.entries(replacements)) {
            line = line.replaceAll(`:${name}`, String(value));
        }

        return line;
    };

    return {
        t,
        locale: props.locale ?? 'en',
        locales: props.locales ?? [],
        isKhmer: (props.locale ?? 'en') === 'km',
    };
}
