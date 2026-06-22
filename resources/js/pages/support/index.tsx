import { Head, router, usePage } from '@inertiajs/react';
import { Check, MessageSquare, Send } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index as supportIndex } from '@/routes/support';
import { store as storeConversation } from '@/routes/support/conversations';
import { store as storeMessage } from '@/routes/support/messages';
import type { Auth, SupportConversation, SupportMessage } from '@/types';

type Props = {
    conversation: SupportConversation | null;
};

function MessageBubble({ message, isMine }: { message: SupportMessage; isMine: boolean }) {
    const time = new Date(message.created_at).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <div className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
            <div className="max-w-[75%] space-y-1">
                {!isMine && message.sender && (
                    <p className="text-muted-foreground px-1 text-xs">{message.sender.full_name}</p>
                )}
                <div
                    className={cn(
                        'rounded-2xl px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap',
                        isMine ? 'bg-primary text-primary-foreground' : 'bg-muted',
                    )}
                >
                    {message.content}
                </div>
                <div
                    className={cn(
                        'flex items-center gap-1 px-1 text-xs text-muted-foreground',
                        isMine ? 'justify-end' : 'justify-start',
                    )}
                >
                    {/* Server (UTC) and browser (local TZ) format this differently — mismatch is intentional */}
                    <span suppressHydrationWarning>{time}</span>
                    {isMine && message.read_at && <Check className="h-3 w-3" />}
                </div>
            </div>
        </div>
    );
}

export default function SupportIndex({ conversation }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [messages, setMessages] = useState<SupportMessage[]>(conversation?.messages ?? []);
    const [content, setContent] = useState('');
    const [sending, setSending] = useState(false);
    const [typingName, setTypingName] = useState<string | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const typingTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const whisperTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [messages.length]);

    useEffect(() => {
        if (!conversation) return;

        const channel = window.Echo.private(`conversation.${conversation.id}`);

        channel.listen('NewSupportMessage', (msg: SupportMessage) => {
            // StrictMode mounts effects twice, which can register two listeners on the same channel.
            // Guard by ID so a message broadcast once can't appear twice.
            setMessages((prev) => (prev.some((m) => m.id === msg.id) ? prev : [...prev, msg]));
        });

        channel.listenForWhisper('typing', ({ name }: { name: string }) => {
            setTypingName(name);
            if (typingTimerRef.current) clearTimeout(typingTimerRef.current);
            typingTimerRef.current = setTimeout(() => setTypingName(null), 3000);
        });

        return () => {
            window.Echo.leave(`conversation.${conversation.id}`);
        };
    }, [conversation?.id]);

    function sendWhisper() {
        if (!conversation) return;
        if (whisperTimerRef.current) clearTimeout(whisperTimerRef.current);
        whisperTimerRef.current = setTimeout(() => {
            window.Echo.private(`conversation.${conversation.id}`).whisper('typing', {
                name: auth.user.full_name,
            });
        }, 300);
    }

    function submit() {
        const trimmed = content.trim();
        if (!trimmed || !conversation || sending) return;

        const optimistic: SupportMessage = {
            id: -Date.now(),
            conversation_id: conversation.id,
            sender_id: auth.user.id,
            content: trimmed,
            read_at: null,
            sender: {
                id: auth.user.id,
                first_name: auth.user.first_name,
                last_name: auth.user.last_name,
                full_name: auth.user.full_name,
            },
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        setMessages((prev) => [...prev, optimistic]);
        setContent('');
        setSending(true);

        router.post(
            storeMessage.url(conversation),
            { content: trimmed },
            {
                preserveScroll: true,
                only: [],
                onFinish: () => setSending(false),
            },
        );
    }

    function handleKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submit();
            return;
        }
        sendWhisper();
    }

    const isClosed = conversation?.status === 'closed';

    return (
        <>
            <Head title="Support" />
            {!conversation ? (
                <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-8">
                    <div className="flex flex-col items-center gap-3 text-center">
                        <div className="bg-muted flex h-12 w-12 items-center justify-center rounded-full">
                            <MessageSquare className="text-muted-foreground h-6 w-6" />
                        </div>
                        <div>
                            <p className="font-medium">No active conversation</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Start a conversation with our support team.
                            </p>
                        </div>
                    </div>
                    <Button onClick={() => router.post(storeConversation.url())}>
                        Start a conversation
                    </Button>
                </div>
            ) : (
                <div className="mx-auto flex h-[calc(100svh-4rem)] w-full max-w-3xl flex-col overflow-hidden">
                    {isClosed && (
                        <div className="bg-muted/50 shrink-0 border-b px-4 py-2 text-center text-sm text-muted-foreground">
                            This conversation has been closed.
                        </div>
                    )}

                    <div
                        ref={scrollRef}
                        className="flex-1 space-y-4 overflow-y-auto px-4 py-4"
                    >
                        {messages.length === 0 && (
                            <div className="text-muted-foreground mt-12 text-center text-sm">
                                No messages yet. Send one below to get started.
                            </div>
                        )}
                        {messages.map((msg) => (
                            <MessageBubble
                                key={msg.id}
                                message={msg}
                                isMine={msg.sender_id === auth.user.id}
                            />
                        ))}
                    </div>

                    {!isClosed && (
                        <div className="shrink-0 border-t px-4 py-3">
                            {typingName && (
                                <p className="text-muted-foreground mb-1.5 text-xs">
                                    {typingName} is typing…
                                </p>
                            )}
                            <div className="flex gap-2">
                                <Textarea
                                    value={content}
                                    onChange={(e) => setContent(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder="Type a message…"
                                    className="max-h-32 resize-none"
                                    disabled={sending}
                                />
                                <Button
                                    onClick={submit}
                                    disabled={!content.trim() || sending}
                                    size="icon"
                                    className="shrink-0 self-end"
                                >
                                    <Send className="h-4 w-4" />
                                </Button>
                            </div>
                            <p className="text-muted-foreground mt-1.5 text-xs">
                                Enter to send · Shift+Enter for a new line
                            </p>
                        </div>
                    )}
                </div>
            )}
        </>
    );
}

SupportIndex.layout = {
    breadcrumbs: [{ title: 'Support', href: supportIndex.url() }],
};
