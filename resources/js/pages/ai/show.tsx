import { Head, router } from '@inertiajs/react';
import { ChevronRight, Loader2, Send } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index as aiRoute } from '@/routes/ai';
import { store as storeConversation } from '@/routes/ai/conversations';
import type { AiConversation, AiMessage } from '@/types';

type Source = { document_title: string; chunk_index: number };

type Props = {
    conversation: AiConversation;
    messages: AiMessage[];
};

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

type BubbleProps = {
    message: AiMessage;
    sources?: Source[];
    isStreaming?: boolean;
};

function MessageBubble({ message, sources, isStreaming }: BubbleProps) {
    const isUser = message.role === 'user';
    const [showSources, setShowSources] = useState(false);

    return (
        <div className={cn('flex', isUser ? 'justify-end' : 'justify-start')}>
            <div className="max-w-[80%] space-y-1.5">
                <div
                    className={cn(
                        'rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                        isUser
                            ? 'bg-primary text-primary-foreground whitespace-pre-wrap'
                            : 'bg-muted',
                    )}
                >
                    {isUser ? (
                        message.content
                    ) : (
                        <ReactMarkdown
                            components={{
                                p: ({ children }) => <p className="mb-2 last:mb-0">{children}</p>,
                                ul: ({ children }) => <ul className="mb-2 list-disc pl-4 last:mb-0">{children}</ul>,
                                ol: ({ children }) => <ol className="mb-2 list-decimal pl-4 last:mb-0">{children}</ol>,
                                li: ({ children }) => <li className="mb-0.5">{children}</li>,
                                strong: ({ children }) => <strong className="font-semibold">{children}</strong>,
                                em: ({ children }) => <em className="italic">{children}</em>,
                            }}
                        >
                            {message.content}
                        </ReactMarkdown>
                    )}
                    {isStreaming && (
                        <span className="ml-0.5 inline-block h-[1em] w-0.5 translate-y-[1px] animate-pulse bg-current" />
                    )}
                </div>

                {sources && sources.length > 0 && (
                    <div>
                        <button
                            onClick={() => setShowSources((v) => !v)}
                            className="text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs transition-colors"
                        >
                            <ChevronRight
                                className={cn(
                                    'h-3 w-3 transition-transform',
                                    showSources && 'rotate-90',
                                )}
                            />
                            {sources.length} source{sources.length !== 1 ? 's' : ''}
                        </button>

                        {showSources && (
                            <div className="bg-muted/50 mt-1 space-y-1 rounded-lg p-2">
                                {sources.map((source, i) => (
                                    <p key={i} className="text-muted-foreground text-xs">
                                        {source.document_title}
                                    </p>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function AiShow({ conversation, messages: initialMessages }: Props) {
    const [messages, setMessages] = useState<AiMessage[]>(initialMessages);
    const [streamingContent, setStreamingContent] = useState<string | null>(null);
    const [sourcesMap, setSourcesMap] = useState<Record<number, Source[]>>({});
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = scrollContainerRef.current;

        if (el) el.scrollTop = el.scrollHeight;
    }, [messages.length, streamingContent]);

    async function sendMessage() {
        const content = input.trim();

        if (!content || isStreaming) return;

        setInput('');
        setIsStreaming(true);
        setStreamingContent('');

        const userMsg: AiMessage = {
            id: -Date.now(), // negative to avoid colliding with real DB ids
            conversation_id: conversation.id,
            role: 'user',
            content,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };
        setMessages((prev) => [...prev, userMsg]);

        let sources: Source[] = [];
        let fullContent = '';

        try {
            const resp = await fetch('/ai/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json, text/event-stream',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ conversation_id: conversation.id, content }),
            });

            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            const reader = resp.body!.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            outer: while (true) {
                const { done, value } = await reader.read();

                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const parts = buffer.split('\n\n');
                buffer = parts.pop() ?? '';

                for (const part of parts) {
                    if (!part.startsWith('data: ')) continue;

                    const data = part.slice(6).trim();

                    if (data === '[DONE]') break outer;

                    try {
                        const event = JSON.parse(data) as
                            | { type: 'sources'; sources: Source[] }
                            | { type: 'delta'; content: string };

                        if (event.type === 'sources') sources = event.sources;
                        else if (event.type === 'delta') {
                            fullContent += event.content;
                            setStreamingContent(fullContent);
                        }
                    } catch {
                        // ignore malformed events
                    }
                }
            }
        } catch {
            fullContent = 'Sorry, something went wrong. Please try again.';
        }

        const assistantId = -(Date.now() + 1);
        const assistantMsg: AiMessage = {
            id: assistantId,
            conversation_id: conversation.id,
            role: 'assistant',
            content: fullContent || '(No response)',
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        setMessages((prev) => [...prev, assistantMsg]);

        if (sources.length) {
            setSourcesMap((prev) => ({ ...prev, [assistantId]: sources }));
        }

        setStreamingContent(null);
        setIsStreaming(false);
    }

    function handleKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void sendMessage();
        }
    }

    return (
        <>
            <Head title="AI Assistant" />
            <div className="flex h-full flex-1 flex-col overflow-hidden">
                {/* Header */}
                <div className="flex shrink-0 items-center justify-between border-b px-4 py-3">
                    <p className="text-muted-foreground text-sm">
                        Ask questions about our products, services, and policies.
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.post(storeConversation.url())}
                    >
                        New Conversation
                    </Button>
                </div>

                {/* Messages */}
                <div
                    ref={scrollContainerRef}
                    className="flex-1 space-y-4 overflow-y-auto px-4 py-4"
                >
                    {messages.length === 0 && streamingContent === null && (
                        <div className="text-muted-foreground mt-12 text-center text-sm">
                            No messages yet. Ask your first question below.
                        </div>
                    )}

                    {messages.map((message) => (
                        <MessageBubble
                            key={message.id}
                            message={message}
                            sources={sourcesMap[message.id]}
                        />
                    ))}

                    {streamingContent !== null && (
                        <MessageBubble
                            message={{
                                id: -1,
                                conversation_id: conversation.id,
                                role: 'assistant',
                                content: streamingContent,
                                created_at: new Date().toISOString(),
                                updated_at: new Date().toISOString(),
                            }}
                            isStreaming
                        />
                    )}
                </div>

                {/* Input */}
                <div className="shrink-0 border-t px-4 py-3">
                    <div className="flex gap-2">
                        <Textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder="Ask a question…"
                            className="max-h-32 resize-none"
                            disabled={isStreaming}
                        />
                        <Button
                            onClick={() => void sendMessage()}
                            disabled={!input.trim() || isStreaming}
                            size="icon"
                            className="shrink-0 self-end"
                        >
                            {isStreaming ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                    <p className="text-muted-foreground mt-1.5 text-xs">
                        Enter to send · Shift+Enter for a new line
                    </p>
                </div>
            </div>
        </>
    );
}

AiShow.layout = {
    breadcrumbs: [{ title: 'AI Assistant', href: aiRoute.url() }],
};
