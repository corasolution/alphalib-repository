import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { UserPlus, UserPlus2, X } from 'lucide-react';
import { FormEventHandler } from 'react';

interface RoleOption {
    value: string;
    label: string;
}

interface Member {
    userId: number;
    name: string;
    email: string;
    roles: RoleOption[];
}

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    expiresAt: string;
}

interface Props {
    journal: { path: string; title: string };
    members: Member[];
    pendingInvitations: PendingInvitation[];
    roles: RoleOption[];
}

export default function JournalPeople({ journal, members, pendingInvitations, roles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'People', href: '#' },
    ];

    const grant = useForm({ email: '', role: '' });
    const account = useForm({ name: '', email: '', role: '' });

    // Revoking goes through router.delete, so its validation errors (notably the last-manager
    // guard) land in Inertia's shared bag rather than either form's — without this the badge
    // just silently stays put with no explanation.
    const revokeError = (usePage().props.errors as Record<string, string> | undefined)?.role;

    const submitGrant: FormEventHandler = (e) => {
        e.preventDefault();
        grant.post(route('journal.people.store', { journal: journal.path }), {
            preserveScroll: true,
            onSuccess: () => grant.reset(),
        });
    };

    const submitAccount: FormEventHandler = (e) => {
        e.preventDefault();
        account.post(route('journal.people.accounts.store', { journal: journal.path }), {
            preserveScroll: true,
            onSuccess: () => account.reset(),
        });
    };

    const revoke = (userId: number, role: string) => {
        router.delete(route('journal.people.destroy', { journal: journal.path, user: userId, role }), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — People`} />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">People</h1>
                    <p className="text-muted-foreground text-sm">
                        Who works on this journal. Roles apply to this journal only — the same person can hold different roles elsewhere.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Members</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {members.length === 0 && <p className="text-muted-foreground text-sm">Nobody has a role on this journal yet.</p>}
                        {members.map((member, index) => (
                            <div key={member.userId}>
                                {index > 0 && <Separator className="mb-3" />}
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-medium">{member.name}</p>
                                        <p className="text-muted-foreground text-xs">{member.email}</p>
                                    </div>
                                    <div className="flex flex-wrap justify-end gap-1.5">
                                        {member.roles.map((role) => (
                                            <Badge key={role.value} variant="secondary" className="gap-1 pr-1">
                                                {role.label}
                                                <button
                                                    type="button"
                                                    aria-label={`Remove ${role.label} from ${member.name}`}
                                                    onClick={() => revoke(member.userId, role.value)}
                                                    className="hover:text-destructive rounded-full"
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ))}
                        <InputError message={revokeError} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <UserPlus className="size-4" />
                            Add someone with an account
                        </CardTitle>
                        <CardDescription>Grants the role straight away.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitGrant} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="grant_email">Email</Label>
                                <Input
                                    id="grant_email"
                                    type="email"
                                    value={grant.data.email}
                                    onChange={(e) => grant.setData('email', e.target.value)}
                                />
                                <InputError message={grant.errors.email} />
                            </div>
                            <div className="grid gap-2 sm:w-52">
                                <Label htmlFor="grant_role">Role</Label>
                                <Select value={grant.data.role} onValueChange={(value) => grant.setData('role', value)}>
                                    <SelectTrigger id="grant_role">
                                        <SelectValue placeholder="Choose a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.value} value={role.value}>
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit" disabled={grant.processing || !grant.data.email || !grant.data.role}>
                                Add
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <UserPlus2 className="size-4" />
                            Create an account
                        </CardTitle>
                        <CardDescription>
                            For people who don't have a login yet. They'll be emailed a link to set their own password — you never see or choose it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={submitAccount} className="space-y-3">
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="account_name">Full name</Label>
                                    <Input id="account_name" value={account.data.name} onChange={(e) => account.setData('name', e.target.value)} />
                                    <InputError message={account.errors.name} />
                                </div>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="account_email">Email</Label>
                                    <Input
                                        id="account_email"
                                        type="email"
                                        value={account.data.email}
                                        onChange={(e) => account.setData('email', e.target.value)}
                                    />
                                    <InputError message={account.errors.email} />
                                </div>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div className="grid gap-2 sm:w-52">
                                    <Label htmlFor="account_role">Role</Label>
                                    <Select value={account.data.role} onValueChange={(value) => account.setData('role', value)}>
                                        <SelectTrigger id="account_role">
                                            <SelectValue placeholder="Choose a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem key={role.value} value={role.value}>
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={account.errors.role} />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={account.processing || !account.data.name || !account.data.email || !account.data.role}
                                >
                                    Create account
                                </Button>
                            </div>
                        </form>

                        {pendingInvitations.length > 0 && (
                            <div className="space-y-2 border-t pt-4">
                                <p className="text-sm font-medium">Pending invitations</p>
                                {pendingInvitations.map((invitation) => (
                                    <div key={invitation.id} className="flex items-center justify-between text-sm">
                                        <span>{invitation.email}</span>
                                        <span className="text-muted-foreground text-xs">
                                            {invitation.role} · expires {new Date(invitation.expiresAt).toLocaleDateString()}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
