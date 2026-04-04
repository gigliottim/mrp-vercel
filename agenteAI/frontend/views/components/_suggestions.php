<div class="agent-suggestions-grid">
    {{# each suggestions }}
        <button class="agent-suggestion-chip" data-intent="{{ intent }}" onclick="selectSuggestion(this)">
            <i class="{{ icon }}"></i>
            <span>{{ label }}</span>
        </button>
        {{/ each }}
</div>
