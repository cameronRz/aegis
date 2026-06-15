import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { redirect as billingPortalRedirect } from '@/actions/App/Http/Controllers/BillingPortalController';
import { cancel as cancelSubscription } from '@/actions/App/Http/Controllers/SubscriptionController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { intervalLabels } from '@/lib/billing';
import { subscriptions as subscriptionsRoute } from '@/routes';
import type { BillingInterval, Subscription, SubscriptionStatus } from '@/types';

type Props = {
    subscriptions: Subscription[];
};

const statusConfig: Record<
    SubscriptionStatus,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    active: { label: 'Active', variant: 'default' },
    trialing: { label: 'Trialing', variant: 'secondary' },
    past_due: { label: 'Past due', variant: 'destructive' },
    canceled: { label: 'Canceled', variant: 'outline' },
    unpaid: { label: 'Unpaid', variant: 'destructive' },
    incomplete: { label: 'Incomplete', variant: 'secondary' },
    incomplete_expired: { label: 'Expired', variant: 'outline' },
    paused: { label: 'Paused', variant: 'outline' },
};

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function billingLabel(sub: Subscription): string | null {
    const interval = sub.product?.billing_interval as BillingInterval | null | undefined;

    if (!interval) return null;

    const count = sub.product?.billing_interval_count ?? 1;
    const label = intervalLabels[interval];

    return count === 1 ? `Billed every ${label}` : `Billed every ${count} ${label}s`;
}

function SubscriptionCard({ sub }: { sub: Subscription }) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [canceling, setCanceling] = useState(false);
    const { label, variant } = statusConfig[sub.status];
    const isCanceled = sub.status === 'canceled';
    const billing = billingLabel(sub);

    function handleCancel() {
        setCanceling(true);
        router.post(
            cancelSubscription(sub).url,
            {},
            {
                onSuccess: () => setCancelOpen(false),
                onFinish: () => setCanceling(false),
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Card>
                <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex flex-col gap-1">
                            <CardTitle className="text-base">{sub.product_name}</CardTitle>
                            {billing && <p className="text-muted-foreground text-sm">{billing}</p>}
                        </div>
                        <Badge variant={variant}>{label}</Badge>
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {sub.trial_ends_at && sub.status === 'trialing' && (
                        <p className="text-muted-foreground text-sm">
                            Trial ends {formatDate(sub.trial_ends_at)}
                        </p>
                    )}

                    {!isCanceled && (
                        <p className="text-muted-foreground text-sm">
                            {sub.cancel_at_period_end
                                ? `Cancels on ${formatDate(sub.current_period_end)}`
                                : `Renews ${formatDate(sub.current_period_end)}`}
                        </p>
                    )}

                    {isCanceled && sub.canceled_at && (
                        <p className="text-muted-foreground text-sm">
                            Canceled on {formatDate(sub.canceled_at)}
                        </p>
                    )}

                    {!isCanceled && !sub.cancel_at_period_end && (
                        <button
                            onClick={() => setCancelOpen(true)}
                            className="text-muted-foreground hover:text-destructive w-fit text-sm transition-colors"
                        >
                            Cancel subscription
                        </button>
                    )}

                    {sub.cancel_at_period_end && (
                        <Badge variant="outline" className="w-fit">
                            Cancels {formatDate(sub.current_period_end)}
                        </Badge>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                title="Cancel subscription"
                description={
                    <>
                        <strong>{sub.product_name}</strong> will remain active until{' '}
                        {formatDate(sub.current_period_end)}, then will not renew.
                    </>
                }
                confirmLabel="Cancel subscription"
                processing={canceling}
                onConfirm={handleCancel}
            />
        </>
    );
}

export default function SubscriptionsIndex({ subscriptions }: Props) {
    const [portalLoading, setPortalLoading] = useState(false);

    const active = subscriptions.filter((s) => s.status !== 'canceled');
    const past = subscriptions.filter((s) => s.status === 'canceled');

    function handleBillingPortal() {
        setPortalLoading(true);
        router.post(billingPortalRedirect.url(), {}, { onFinish: () => setPortalLoading(false) });
    }

    return (
        <>
            <Head title="Subscriptions" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {active.length === 0 ? 'No active subscriptions.' : `${active.length} active subscription${active.length !== 1 ? 's' : ''}.`}
                    </p>
                    <Button variant="outline" size="sm" disabled={portalLoading} onClick={handleBillingPortal}>
                        {portalLoading ? 'Redirecting…' : 'Manage billing'}
                    </Button>
                </div>

                {active.length > 0 && (
                    <div className="flex flex-col gap-3">
                        {active.map((sub) => (
                            <SubscriptionCard key={sub.id} sub={sub} />
                        ))}
                    </div>
                )}

                {past.length > 0 && (
                    <div className="flex flex-col gap-3">
                        <h2 className="text-muted-foreground text-sm font-medium uppercase tracking-wide">
                            Past subscriptions
                        </h2>
                        {past.map((sub) => (
                            <SubscriptionCard key={sub.id} sub={sub} />
                        ))}
                    </div>
                )}

                {subscriptions.length === 0 && (
                    <div className="text-muted-foreground py-12 text-center text-sm">
                        You don't have any subscriptions yet.
                    </div>
                )}
            </div>
        </>
    );
}

SubscriptionsIndex.layout = {
    breadcrumbs: [{ title: 'Subscriptions', href: subscriptionsRoute.url() }],
};
