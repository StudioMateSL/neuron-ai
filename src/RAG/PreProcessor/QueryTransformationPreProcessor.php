<?php

namespace NeuronAI\RAG\PreProcessor;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\SystemPrompt;

class QueryTransformationPreProcessor implements PreProcessorInterface
{
    /**
     * Creates a new query transformation preprocessor.
     *
     * @param AIProviderInterface $provider The AI provider for query transformation
     * @param QueryTransformationType $queryTransformation The transformation strategy
     * @param string|null $customInstructions Custom system prompt to override built-in transformations
     */
    public function __construct(
        protected AIProviderInterface $provider,
        protected QueryTransformationType $queryTransformation = QueryTransformationType::REWRITING,
        protected ?string $customInstructions = null,
    ) {
    }

    /**
     * Transforms a user query for optimized RAG document retrieval.
     *
     * Applies the configured transformation strategy or uses custom instructions
     * if provided to enhance query effectiveness.
     *
     * @param Message $question The original user query to transform
     * @return Message The transformed query optimized for document retrieval
     */
    public function process(Message $question): Message
    {
        $preparedMessage = $this->prepareMessage($question);

        return $this->provider
            ->systemPrompt($this->getInstructions())
            ->chat([$preparedMessage]);
    }

    public function getInstructions(): string
    {
        if (isset($this->customInstructions)) {
            return $this->customInstructions;
        }

        return match ($this->queryTransformation) {
            QueryTransformationType::REWRITING => $this->getRewritingPrompt(),
            QueryTransformationType::DECOMPOSITION => $this->getDecompositionPrompt(),
            QueryTransformationType::HYDE => $this->getHydePrompt(),
        };
    }

    public function setCustomInstructions(string $instructions): self
    {
        $this->customInstructions = $instructions;
        return $this;
    }

    public function setQueryTransformation(QueryTransformationType $queryTransformation): self
    {
        $this->queryTransformation = $queryTransformation;
        return $this;
    }

    public function setProvider(AIProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    protected function prepareMessage(Message $question): UserMessage
    {
        return new UserMessage('<original-query>' . $question->getContent() . '</original-query>');
    }

    protected function getRewritingPrompt(): string
    {
        return new SystemPrompt(
            background: [
                'You are an AI assistant tasked with reformulating user queries to improve retrieval in a RAG system'
            ],
            steps: [
                'Given the original query, rewrite it to be more specific, detailed, and likely to retrieve relevant information.',
                'Focus on expanding vocabulary and technical terminology while preserving the original intent and meaning.',
            ],
            output: [
                'Output only the reformulated query',
                'Do not add temporal references, dates, or years unless they are present in the original query'
            ]
        );
    }

    protected function getDecompositionPrompt(): string
    {
        return new SystemPrompt(
            background: [
                'You are an AI assistant that breaks down complex queries into simpler sub-queries for comprehensive information retrieval in a RAG system'
            ],
            steps: [
                'Given the original complex query, decompose it into 2-4 simpler, focused sub-queries.',
                'Each sub-query should address a specific aspect of the original question.',
                'Ensure all sub-queries together cover the full scope of the original query.',
            ],
            output: [
                'Output each sub-query on a separate line',
                'Use clear, specific language for each sub-query',
                'Do not add temporal references, dates, or years unless they are present in the original query',
            ]
        );
    }

    protected function getHydePrompt(): string
    {
        return new SystemPrompt(
            background: [
                'You are an AI assistant that generates hypothetical answer to the user query, to improve retrieval in a RAG system'
            ],
            steps: [
                'Given the original query, write a hypothetical document passage that would directly answer this question.',
                'Create content that resembles what you would expect to find in a relevant document.',
            ],
            output: [
                'Output only the hypothetical document passage',
                'Do not add temporal references, dates, or years unless they are present in the original query',
                'Keep the response concise: maximum 150 words'
            ]
        );
    }
}
