import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FormEventHandler } from 'react';

interface ReviewFormElement {
    id: number;
    type: 'text' | 'textarea' | 'radio' | 'checkbox' | 'rating';
    label: string;
    helpText: string | null;
    isRequired: boolean;
    options: string[] | null;
}

interface Recommendation {
    value: string;
    label: string;
}

interface Props {
    journal: string;
    reviewAssignment: {
        id: number;
        dueAt: string | null;
        respondedAt: string | null;
        declinedAt: string | null;
        completedAt: string | null;
        recommendation: string | null;
    };
    submission: {
        title: string;
        abstract: string | null;
    };
    manuscript: { originalName: string; downloadUrl: string } | null;
    reviewForm: { title: string; elements: ReviewFormElement[] } | null;
    recommendations: Recommendation[];
}

function ElementInput({ element, value, onChange }: { element: ReviewFormElement; value: unknown; onChange: (value: unknown) => void }) {
    if (element.type === 'text') {
        return (
            <input
                className="border-input bg-background rounded-md border px-3 py-2 text-sm shadow-sm"
                value={(value as string) ?? ''}
                onChange={(e) => onChange(e.target.value)}
            />
        );
    }

    if (element.type === 'textarea') {
        return (
            <textarea
                className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm shadow-sm"
                value={(value as string) ?? ''}
                onChange={(e) => onChange(e.target.value)}
            />
        );
    }

    if (element.type === 'radio' || element.type === 'rating') {
        return (
            <div className="flex flex-wrap gap-2">
                {(element.options ?? []).map((option) => (
                    <button
                        key={option}
                        type="button"
                        onClick={() => onChange(option)}
                        className={`rounded-md border px-3 py-1 text-sm ${value === option ? 'bg-foreground text-background' : ''}`}
                    >
                        {option}
                    </button>
                ))}
            </div>
        );
    }

    // checkbox
    const selected = Array.isArray(value) ? (value as string[]) : [];

    return (
        <div className="flex flex-wrap gap-2">
            {(element.options ?? []).map((option) => {
                const checked = selected.includes(option);

                return (
                    <button
                        key={option}
                        type="button"
                        onClick={() => onChange(checked ? selected.filter((o) => o !== option) : [...selected, option])}
                        className={`rounded-md border px-3 py-1 text-sm ${checked ? 'bg-foreground text-background' : ''}`}
                    >
                        {option}
                    </button>
                );
            })}
        </div>
    );
}

export default function ReviewShow({ journal, reviewAssignment, submission, manuscript, reviewForm, recommendations }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Review', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm<{ recommendation: string; answers: Record<number, unknown> }>({
        recommendation: reviewAssignment.recommendation ?? '',
        answers: {},
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.reviews.store', { journal, reviewAssignment: reviewAssignment.id }));
    };

    if (reviewAssignment.declinedAt) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Review declined" />
                <div className="p-4">
                    <p className="text-muted-foreground text-sm">You declined this review invitation.</p>
                </div>
            </AppLayout>
        );
    }

    if (!reviewAssignment.respondedAt) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Review invitation pending" />
                <div className="p-4">
                    <p className="text-muted-foreground text-sm">
                        Accept this review invitation from the email you were sent before you can submit a review.
                    </p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={submission.title} />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{submission.title}</h1>
                    {submission.abstract && <p className="text-muted-foreground mt-2 text-sm">{submission.abstract}</p>}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Manuscript</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {manuscript ? (
                            <a
                                href={manuscript.downloadUrl}
                                className="text-foreground inline-flex items-center gap-2 text-sm underline underline-offset-4"
                            >
                                <Download className="size-4" />
                                {manuscript.originalName}
                            </a>
                        ) : (
                            <p className="text-muted-foreground text-sm">No manuscript uploaded yet.</p>
                        )}
                    </CardContent>
                </Card>

                {reviewAssignment.completedAt ? (
                    <Card>
                        <CardContent className="pt-6">
                            <Badge variant="secondary">Review submitted</Badge>
                            <p className="text-muted-foreground mt-2 text-sm">Thank you — your review has been recorded.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        {reviewForm && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{reviewForm.title}</CardTitle>
                                    <CardDescription>Fields marked required must be filled in before you can submit.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {reviewForm.elements.map((element) => (
                                        <div key={element.id} className="grid gap-2">
                                            <Label>
                                                {element.label}
                                                {element.isRequired && <span className="text-destructive"> *</span>}
                                            </Label>
                                            {element.helpText && <p className="text-muted-foreground text-xs">{element.helpText}</p>}
                                            <ElementInput
                                                element={element}
                                                value={data.answers[element.id]}
                                                onChange={(value) => setData('answers', { ...data.answers, [element.id]: value })}
                                            />
                                            <InputError message={(errors as Record<string, string>).answers} />
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Recommendation</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Select value={data.recommendation} onValueChange={(value) => setData('recommendation', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Choose a recommendation" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {recommendations.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>
                                                {r.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.recommendation} className="mt-2" />
                            </CardContent>
                        </Card>

                        <Button type="submit" disabled={processing || !data.recommendation}>
                            Submit review
                        </Button>
                    </form>
                )}
            </PageContainer>
        </AppLayout>
    );
}
