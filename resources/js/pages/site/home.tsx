import AcademicTemplate from './templates/academic';
import BlueTemplate from './templates/blue';
import BoldTemplate from './templates/bold';
import ClassicTemplate from './templates/classic';
import MagazineTemplate from './templates/magazine';
import MinimalTemplate from './templates/minimal';
import { type TemplateProps } from './templates/types';

/**
 * The site landing page, which is whichever template the admin selected.
 *
 * A registry rather than five Inertia components: the controller stays ignorant of template
 * internals, the page keeps one component name (so nothing downstream cares that this is
 * themeable), and adding a sixth design is one import and one map entry.
 *
 * Keys must match SiteTheme::TEMPLATES. The server already falls back to `academic` for an
 * unknown key, and this falls back again — a missing entry here would otherwise render a blank
 * page, which is a worse failure than showing the default design.
 */
const TEMPLATES = {
    academic: AcademicTemplate,
    classic: ClassicTemplate,
    magazine: MagazineTemplate,
    minimal: MinimalTemplate,
    bold: BoldTemplate,
    blue: BlueTemplate,
} as const;

export default function SiteHome({ template, ...props }: TemplateProps & { template: string }) {
    const Template = TEMPLATES[template as keyof typeof TEMPLATES] ?? AcademicTemplate;

    return <Template {...props} />;
}
