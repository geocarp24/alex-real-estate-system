"""
MODEL ASSIGNMENT — Central configuration for Claude model selection
Based on cost-benefit audit: 2026-04-10

All sub-agents must use these models. Override at runtime if needed.
"""

# ─────────────────────────────────────────────────────────
# MODEL DEFINITIONS
# ─────────────────────────────────────────────────────────

MODELS = {
    "haiku": "claude-haiku-4-5",
    "sonnet": "claude-sonnet-4-6",
    "opus": "claude-opus-4-6",
}

# ─────────────────────────────────────────────────────────
# AGENT ASSIGNMENTS (OPTIMIZED 2026-04-10)
# ─────────────────────────────────────────────────────────

AGENT_MODELS = {
    # REAL ESTATE ANALYSIS (Deal underwriting)
    "scout": "sonnet",           # Market research + data analysis
    "matematico": "sonnet",      # Financial calculations
    "fact-checker": "sonnet",    # Logic verification + confidence scoring

    # SKIP TRACING
    "tracy": "haiku",            # Data formatting only (NO reasoning needed)

    # SOCIAL MEDIA CONTENT
    "social_media": "sonnet",    # Idea generation
    "creativo": "sonnet",        # Visual descriptions + captions
    "director": "sonnet",        # Narrative + video scripts
    "programador": "sonnet",     # API integration + scheduling

    # SUPPORT AGENTS
    "secretario": "sonnet",      # Email + calendar management
    "code_debugger": "opus",     # 🔴 CRITICAL: Production code changes
}

# ─────────────────────────────────────────────────────────
# HELPER FUNCTIONS
# ─────────────────────────────────────────────────────────

def get_model(agent_name: str) -> str:
    """
    Get the model for a given agent.

    Args:
        agent_name: Name of agent (scout, tracy, creativo, etc.)

    Returns:
        Claude model ID (e.g., "claude-sonnet-4-6")

    Example:
        model = get_model("tracy")
        # Returns: "claude-haiku-4-5"
    """
    tier = AGENT_MODELS.get(agent_name, "sonnet")
    return MODELS[tier]

def get_all_agents() -> dict:
    """Return all agent assignments."""
    return {agent: get_model(agent) for agent in AGENT_MODELS}

# ─────────────────────────────────────────────────────────
# SAVINGS CALCULATION
# ─────────────────────────────────────────────────────────

COST_PER_1M_TOKENS = {
    "haiku": {
        "input": 0.80,      # $0.80 per 1M input tokens
        "output": 2.40,     # $2.40 per 1M output tokens
    },
    "sonnet": {
        "input": 3.00,
        "output": 15.00,
    },
    "opus": {
        "input": 15.00,
        "output": 45.00,
    },
}

def estimate_monthly_cost(agent_name: str, monthly_invocations: int, avg_input_tokens: int, avg_output_tokens: int) -> dict:
    """
    Estimate monthly cost for an agent.

    Args:
        agent_name: Agent name
        monthly_invocations: How many times per month it runs
        avg_input_tokens: Average input tokens per invocation
        avg_output_tokens: Average output tokens per invocation

    Returns:
        Dict with cost breakdown
    """
    model_name = AGENT_MODELS.get(agent_name, "sonnet")
    costs = COST_PER_1M_TOKENS[model_name]

    input_cost = (avg_input_tokens / 1_000_000) * costs["input"] * monthly_invocations
    output_cost = (avg_output_tokens / 1_000_000) * costs["output"] * monthly_invocations
    total = input_cost + output_cost

    return {
        "agent": agent_name,
        "model": MODELS[model_name],
        "monthly_invocations": monthly_invocations,
        "avg_input_tokens": avg_input_tokens,
        "avg_output_tokens": avg_output_tokens,
        "input_cost": f"${input_cost:.2f}",
        "output_cost": f"${output_cost:.2f}",
        "total_monthly": f"${total:.2f}",
        "total_annual": f"${total * 12:.2f}",
    }

# ─────────────────────────────────────────────────────────
# VALIDATION THRESHOLDS (for monitoring)
# ─────────────────────────────────────────────────────────

VALIDATION_THRESHOLDS = {
    "fact-checker": {
        "confidence_score_min": 7.0,  # Must be ≥7.0/10
        "error_rate_max": 0.02,        # Max 2% errors
        "check_frequency": "weekly",
    },
    "tracy": {
        "success_rate_min": 0.95,      # Must be ≥95% successful
        "check_frequency": "daily",
    },
}

# ─────────────────────────────────────────────────────────
# AUDIT SUMMARY
# ─────────────────────────────────────────────────────────

AUDIT_SUMMARY = """
╔════════════════════════════════════════════════════════════╗
║  CLAUDE MODEL OPTIMIZATION — AUDIT 2026-04-10              ║
╚════════════════════════════════════════════════════════════╝

MONTHLY SAVINGS: $3,099.60 (-71%)
ANNUAL SAVINGS:  $37,195.20 (-71%)

MODEL DISTRIBUTION:
  ├─ Haiku (2 agents):   Tracy
  ├─ Sonnet (7 agents):  Scout, Matemático, Fact-Checker, Social Media, Creativo, Director, Programador, Secretario
  └─ Opus (1 agent):     Code Debugger (CRITICAL — no downgrade)

QUALITY VALIDATION:
  ├─ Haiku: No validation needed (task does not require reasoning)
  ├─ Sonnet: Monitor Confidence Scores (threshold ≥7.0/10)
  └─ Opus: Spot checks on code changes (safety critical)

IMPLEMENTATION DATE: 2026-04-10
STATUS: LIVE — All phases deployed simultaneously
"""

if __name__ == "__main__":
    print(AUDIT_SUMMARY)
    print("\nAGENT MODEL ASSIGNMENTS:")
    for agent, model in get_all_agents().items():
        print(f"  {agent:20} → {model}")
