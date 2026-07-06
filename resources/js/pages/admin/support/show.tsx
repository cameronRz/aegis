import { Head, router, usePage } from '@inertiajs/react';
import { Check, Send } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { ClientDate } from '@/components/client-date';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index as adminSupportIndex, close as closeConversation } from '@/routes/admin/support';
import { store as storeMessage } from '@/routes/support/messages';
import type { Auth, SupportConversation, SupportMessage, User } from '@/types';

type ConversationWithDetails = SupportConversation & {
    messages: SupportMessage[];
    client: Pick<User, 'id' | 'first_name' | 'last_name' | 'full_name'>;
};

type Props = {
    conversation: ConversationWithDetails;
};

function MessageBubble({ message, isMine }: { message: SupportMessage; isMine: boolean }) {
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
                    <ClientDate iso={message.created_at} options={{ hour: '2-digit', minute: '2-digit' }} />
                    {isMine && message.read_at && <Check className="h-3 w-3" />}
                </div>
            </div>
        </div>
    );
}

export default function AdminSupportShow({ conversation }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [messages, setMessages] = useState<SupportMessage[]>(conversation.messages);
    const [content, setContent] = useState('');
    const [sending, setSending] = useState(false);
    const [typingName, setTypingName] = useState<string | null>(null);
    const [closeOpen, setCloseOpen] = useState(false);
    const [closing, setClosing] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const typingTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const whisperTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const el = scrollRef.current;

        if (el) el.scrollTop = el.scrollHeight;
    }, [messages.length]);

    useEffect(() => {
        textareaRef.current?.focus();
    }, []);

    useEffect(() => {
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
    }, [conversation.id]);

    function sendWhisper() {
        if (whisperTimerRef.current) clearTimeout(whisperTimerRef.current);

        whisperTimerRef.current = setTimeout(() => {
            window.Echo.private(`conversation.${conversation.id}`).whisper('typing', {
                name: auth.user.full_name,
            });
        }, 300);
    }

    function submit() {
        const trimmed = content.trim();

        if (!trimmed || sending) return;

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
                onFinish: () => {
                    setSending(false);
                    // Defer focus so React re-renders first and removes `disabled` before focus.
                    setTimeout(() => textareaRef.current?.focus(), 0);
                },
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

    function handleClose() {
        setClosing(true);
        router.post(closeConversation(conversation).url, {}, {
            preserveScroll: true,
            onSuccess: () => setCloseOpen(false),
            onFinish: () => setClosing(false),
        });
    }

    const isClosed = conversation.status === 'closed';

    return (
        <>
            <Head title={`Support — ${conversation.client.full_name}`} />
            <div className="mx-auto flex h-[calc(100svh-4rem)] w-full max-w-3xl flex-col overflow-hidden">
                {/* Header */}
                <div className="flex shrink-0 items-center justify-between border-b px-4 py-3">
                    <div className="flex items-center gap-3">
                        <span className="font-medium">{conversation.client.full_name}</span>
                        <Badge variant={isClosed ? 'outline' : 'default'}>
                            {isClosed ? 'Closed' : 'Open'}
                        </Badge>
                    </div>
                    {!isClosed && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setCloseOpen(true)}
                        >
                            Close conversation
                        </Button>
                    )}
                </div>

                {/* Messages */}
                <div
                    ref={scrollRef}
                    className="flex-1 space-y-4 overflow-y-auto px-4 py-4"
                >
                    {messages.length === 0 && (
                        <div className="text-muted-foreground mt-12 text-center text-sm">
                            No messages yet.
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

                {/* Input */}
                {!isClosed ? (
                    <div className="shrink-0 border-t px-4 py-3">
                        {typingName && (
                            <p className="text-muted-foreground mb-1.5 text-xs">
                                {typingName} is typing…
                            </p>
                        )}
                        <div className="flex gap-2">
                            <Textarea
                                ref={textareaRef}
                                value={content}
                                onChange={(e) => setContent(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder="Reply…"
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
                ) : (
                    <div className="bg-muted/50 shrink-0 border-t px-4 py-3 text-center text-sm text-muted-foreground">
                        This conversation has been closed.
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={closeOpen}
                onOpenChange={setCloseOpen}
                title="Close Conversation"
                alertTitle="Close this conversation?"
                description="The client will no longer be able to send messages. This cannot be undone."
                confirmLabel="Close"
                processing={closing}
                onConfirm={handleClose}
            />
        </>
    );
}

AdminSupportShow.layout = {
    breadcrumbs: [
        { title: 'Support', href: adminSupportIndex.url() },
        { title: 'Conversation' },
    ],
};
