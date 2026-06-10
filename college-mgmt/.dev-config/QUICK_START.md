# Quick Start: Automated Token Optimization

## Installation Complete! 🎉

Your token optimization system is ready. Here's how to use it:

### 1. Load the aliases (one time):
```bash
source ~/.bashrc  # or ~/.zshrc
```

### 2. Start developing a feature:
```bash
feature "feature_name"
# Example: feature "elective_management"
```

This will:
- Update graphify (0 tokens)
- Generate architecture queries
- Create code search patterns
- Generate a ready-to-paste prompt template

### 3. Copy the prompt to Claude Code:
- Open Claude Code chat
- Type: `/clear` (start fresh)
- Paste the PROMPT_TEMPLATE.md content
- Ask your question
- Implement the feature

### 4. Available Commands:

```bash
# Start a new feature (complete setup)
feature "feature_name"

# Before every chat (refresh context)
ctx

# Search for code (0 tokens)
analyze "search_term"

# Query architecture (0 tokens)
graph "What models handle [domain]?"

# Update graphify cache
gupdate
```

### 5. Automatic Features:

- ✅ Pre-commit hook updates graphify automatically
- ✅ GitHub Actions auto-updates on main branch
- ✅ Smart prompt templates ready to paste
- ✅ Code search patterns (grep) ready to run

### Expected Results:

| Metric | Before | After | Savings |
|--------|--------|-------|---------|
| Per feature | 3,000 tokens | 400 tokens | 87% |
| Per week | 50,000 tokens | 15,000 tokens | 70% |

---

## Common Workflows

### Building a New Feature (10 minutes)

```bash
# 1. Prepare context (1 minute)
feature "student_resume_builder"

# 2. Setup Claude Code (1 minute)
# - Type: /clear
# - Paste PROMPT_TEMPLATE.md
# - /fast

# 3. Implement (5 minutes)
# - Copy prompt output to Claude
# - Ask specific question
# - Claude gives design/code
# - Implement locally

# 4. Review (1-2 minutes)
ctx  # Refresh context
# Ask final questions in Claude
```

### Finding Code (0 tokens)

```bash
# Instead of chatting "How is Student model structured?"
analyze "Student"

# Shows:
# - Models with Student name
# - Controllers handling students
# - Routes with students
# - Graphify relationships
```

### Quick Architecture Check (0 tokens)

```bash
# Instead of chatting "How do enrollments work?"
graph "How do student enrollments work?"

# Instant graphify results, paste in Claude
```

### Before Code Review (2 minutes)

```bash
ctx  # Refresh everything
# Lists pre-chat checklist
# Shows token-saving tips
# Ready for focused review chat
```

---

## What Happens Automatically

1. **Every commit**: Graphify cache updates automatically
2. **Every push to main**: GitHub Actions updates graph
3. **Every feature start**: Context prepared, 0 tokens
4. **Every chat**: Pre-chat checklist reminds you

---

## Troubleshooting

### Graphify not found?
```bash
uv tool install graphifyy
```

### Aliases not working?
```bash
source ~/.bashrc  # Reload shell profile
```

### Pre-commit hook not running?
```bash
chmod +x .git/hooks/pre-commit
```

---

## Token Savings Example

**Before Automation:**
```
Chat 1: "Explain Student model" (400 tokens)
Chat 2: "Show relationships" (300 tokens)
Chat 3: "How do enrollments work?" (350 tokens)
Chat 4: Design review (400 tokens)
Chat 5: Code review (300 tokens)
─────────────────────────────
Total: 1,750 tokens
```

**After Automation:**
```
Terminal: feature "student_management" (0 tokens)
Chat 1: Paste template + specific design (200 tokens)
Chat 2: Code review question (100 tokens)
─────────────────────────────
Total: 300 tokens

Savings: 83%
```

---

**You're ready! Start with:** `feature "your_feature_name"`
