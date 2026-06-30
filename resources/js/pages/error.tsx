import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes';

const statusConfig: Record<number, { title: string; description: string }> = {
    403: { title: 'Forbidden', description: "You don't have permission to access this resource." },
    404: { title: 'Page Not Found', description: "The page you're looking for doesn't exist." },
    500: { title: 'Server Error', description: 'Something went wrong on our end. Please try again later.' },
    503: { title: 'Service Unavailable', description: 'This feature is currently unavailable.' },
};

export default function ErrorPage({ status }: { status: number }) {
    const { title, description } = statusConfig[status] ?? {
        title: 'Error',
        description: 'An unexpected error occurred.',
    };

    return (
        <>
            <Head title={`${status} ${title}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-foreground">
                <div className="space-y-4 text-center">
                    <p className="text-muted-foreground text-8xl font-bold">{status}</p>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">{description}</p>
                    <Link href={dashboard()} className="text-primary mt-4 inline-block text-sm hover:underline">
                        Return to Dashboard
                    </Link>
                </div>
            </div>
        </>
    );
}

ErrorPage.layout = null;
