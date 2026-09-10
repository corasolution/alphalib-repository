import { useSystemBranding } from '@/lib/branding';
import AppLogoIcon from './app-logo-icon';

/**
 * The system's own identity in the staff sidebar.
 *
 * Reads the name and logo a site admin set in CMS → Homepage rather than hardcoding them: this is
 * the customer's platform, and until now it introduced itself to their editors as "Laravel
 * Starter Kit". Falls back to the bundled mark when no logo has been uploaded, so a fresh install
 * still looks deliberate.
 */
export default function AppLogo() {
    const { name, logoUrl } = useSystemBranding();

    return (
        <>
            {/*
              An uploaded logo gets a white plate rather than the blue chip. The chip exists to
              give the bundled monochrome mark something to sit on; a customer logo that is itself
              dark, or transparent with dark ink, disappears against it entirely — which is what
              happened the first time one was uploaded here.
            */}
            <div
                className={
                    'flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md ' +
                    (logoUrl ? 'bg-white p-1' : 'bg-sidebar-primary text-sidebar-primary-foreground')
                }
            >
                {logoUrl ? (
                    <img src={logoUrl} alt="" className="size-full object-contain" />
                ) : (
                    <AppLogoIcon className="text-sidebar-primary-foreground size-5 fill-current" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">{name}</span>
            </div>
        </>
    );
}
