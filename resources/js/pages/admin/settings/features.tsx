import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { Features } from '@/types/auth';

type Props = {
    features: {
        aiAssistantEnabled: boolean;
        supportChatEnabled: boolean;
    };
};

function FeatureToggle({
    id,
    label,
    description,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4 py-4">
            <div className="space-y-0.5">
                <Label htmlFor={id} className="text-base">
                    {label}
                </Label>
                <p className="text-muted-foreground text-sm">{description}</p>
            </div>
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
        </div>
    );
}

export default function FeaturesSettings({ features }: Props) {
    function toggle(key: 'ai_assistant_enabled' | 'support_chat_enabled', value: boolean) {
        router.patch(
            '/admin/settings/features',
            { [key]: value },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Setting saved'),
            },
        );
    }

    return (
        <>
            <Head title="Feature Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Feature Toggles</CardTitle>
                        <CardDescription>
                            Enable or disable features globally for all users, regardless of their
                            permissions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <FeatureToggle
                            id="ai-assistant"
                            label="AI Assistant"
                            description="Allow clients to use the AI chat assistant."
                            checked={features.aiAssistantEnabled}
                            onChange={(value) => toggle('ai_assistant_enabled', value)}
                        />
                        <FeatureToggle
                            id="support-chat"
                            label="Support Chat"
                            description="Allow clients to open support conversations with your team."
                            checked={features.supportChatEnabled}
                            onChange={(value) => toggle('support_chat_enabled', value)}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

FeaturesSettings.layout = {
    breadcrumbs: [{ title: 'Feature Settings', href: '/admin/settings/features' }],
};
