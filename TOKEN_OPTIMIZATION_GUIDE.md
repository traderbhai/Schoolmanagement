# Token Optimization Guide for Claude Code Development

> **Goal:** Reduce context window size by 60-80% through strategic practices, without sacrificing code quality.

---

## 1. Claude Code Settings (Immediate Impact)

### Enable "Fast Mode"
```bash
# In Claude Code terminal:
/fast
```
**Impact:** Uses Opus model (faster output) instead of slower context-heavy responses.

### Disable Auto-Context Features
Most Claude Code setups have features that add unnecessary context:

**Settings to check/disable:**
- ❌ "Always include full file contents" → Only include relevant sections
- ❌ "Auto-load related files" → Load manually only when needed
- ❌ "Include git history" → Only request specific commits
- ❌ "Auto-include test files" → Only when relevant
- ❌ "Full error stack traces" → Only first 50 lines needed

---

## 2. Graphify-First Development (Most Effective)

**Use graphify to replace 100+ tokens of explanation per message.**

### Before Asking Claude (5 minute setup):
```bash
# 1. Get architecture context
graphify query "What models are involved in [feature]?"

# 2. Show recent patterns
graphify query "How do other portals structure [similar feature]?"

# 3. Check dependencies
graphify query "Which services use [model]?"
```

### In Claude Chat (Minimal tokens):
```
Graphify shows: [paste result here - ONE LINE, not explanation]

I want to [implement feature].
Here's my approach: [brief code snippet]
Does this fit?
```

**Token savings example:**
```
❌ Without graphify (350 tokens):
"We have a Student model that has many enrollments...
The Teacher model has various relationships...
The Batch model groups students...
Our database structure uses..."

✅ With graphify (20 tokens):
"Graphify shows: Student → Enrollment → Subject → Teacher
"
```

**Savings: 330 tokens per explanation!**

---

## 3. Smart File Selection

### ❌ DON'T
```bash
# Never do this - adds entire file to context
"Read college-mgmt/app/Models/Student.php and tell me..."
```

### ✅ DO
```bash
# Specific line ranges only
# Request just the methods you need
"Show me the `hasMany` relationships in Student.php (lines 1-50)"

# Or ask Claude to check specific methods
"In StudentController, what does the `index()` method return?"
```

**Impact:** 70% smaller context window

---

## 4. Conversation Structure (Critical)

### Strategy A: Single-Focus Conversations
**Create separate chats for different tasks:**
- Chat 1: "Fix bug in authentication"
- Chat 2: "Add feature X to Student portal"
- Chat 3: "Refactor PMC dashboard"

**Why:** Each chat starts fresh, no history bloat

**Token cost per chat:**
- Chat with 20 messages: ~5,000 tokens in history
- Split into 3 chats of 7 messages each: ~8,500 tokens total in histories
- **Savings: ~1,500 tokens across conversation**

### Strategy B: Clear Context Boundaries
```
❌ Message 1: "Here's my project structure, let me explain..."
              (provides 500 tokens of context)

❌ Message 2: "Now add this new feature..."
              (repeats all 500 tokens from message 1)

✅ Message 1: "github.com/... repo structure" (20 tokens reference)
✅ Message 2: "As discussed in message 1, add..." (5 tokens reference)
```

**Impact:** 90% reduction in repeated context

### Strategy C: Use @mentions Instead of Repeating
```
❌ "I have this controller... here's the code... this method does..."
   (controller details repeated)

✅ "@college-mgmt/app/Http/Controllers/Student/StudentController.php
   In the show() method, how should I add..."
   (Claude loads it on demand)
```

---

## 5. Clear, Specific Requests

### ❌ Vague Requests (Cost 400+ tokens due to clarifications)
```
"I'm building a feature for student management. 
Can you help me design the models and controllers?"
```
→ Claude asks: What exactly? What do students need to manage?

### ✅ Specific Requests (Cost 100 tokens, no clarification)
```
"Add a StudentResume model with upload/delete.
Controller: ResumeController@store, @destroy.
Routes: POST /student/resume, DELETE /student/resume/{id}.
Use StudentController as pattern."
```
→ Claude implements directly, no questions

**Savings: 300 tokens per request**

---

## 6. Code Snippets (Not Full Files)

### When Asking About Code

❌ **DON'T:**
```bash
"Read this entire 200-line controller file"
# Loads entire file context (200 lines = ~400 tokens)
```

✅ **DO:**
```bash
# Just the relevant method
"In StudentController, the index() method has this logic:
```php
$students = Student::where('batch_id', $batch)
    ->with('enrollments')
    ->paginate(20);
return view('students.index', compact('students'));
```

Can I also add a filter for academic_status?"
```

**Impact:** 75% less context

---

## 7. Use Claude Code Features Efficiently

### Read Only What You Need
```bash
# Instead of: "Read Student.php"
# Do this: Find specific thing first

# Option 1: Grep for method
grep -n "function hasMany" college-mgmt/app/Models/Student.php

# Option 2: Ask specific question
"Does Student model have a 'courses' relationship?"

# Option 3: Check specific line range
"Show me lines 50-80 of Student.php"
```

### Avoid Repository Browsing
```bash
❌ "Show me the directory structure of the project"
   (generates huge listing)

✅ "What files are in app/Http/Controllers/Student/?"
   (minimal, targeted response)
```

---

## 8. Session Management

### Keep Sessions Short
**Best practice:** 1 session = 1 feature or 1 bug fix

**Session lifecycle:**
1. Start new session → /clear history
2. Work on ONE thing only
3. Complete + commit
4. End session
5. Start fresh session for next task

**Token usage:**
- Long session (50+ messages): 50K+ tokens in history
- 5 short sessions (10 messages each): 5K per session, fresh start

### Clear History When Switching Topics
```bash
# In Claude Code
/clear

# This resets the conversation history
# Start fresh for new feature
```

**Impact:** Prevents context bloat

---

## 9. Smart Error Handling

### ❌ Don't Paste Entire Stack Traces
```
[Entire 100-line stack trace]
```
→ 200+ tokens wasted

### ✅ Do This
```
Error: SQLSTATE[42S22]: Column not found: 1054 
Unknown column 'leave_applications.student_id' in 'where clause'

At: college-mgmt/app/Models/Student.php:45
In: hasMany('LeaveApplication')

Question: Why is student_id missing from leave_applications table?
```

**Savings: 150 tokens**

---

## 10. Leverage Graphify + Search Instead of Chat

### When You Could Ask Claude, Use This Instead

❌ Chat:
```
"What models reference the Batch model?"
(Claude explains, adds 200+ tokens)
```

✅ Use Graphify:
```bash
graphify query "What models depend on Batch?"
# Instant, zero tokens
```

### Migration Strategy
| Task | Old Way | New Way | Savings |
|------|---------|---------|---------|
| Find relationships | Chat (200 tokens) | graphify query | 100% |
| Understand routes | Chat + read routes | grep routes + graphify | 90% |
| Check implementation | Chat (300 tokens) | grep code | 100% |
| Design feature | Chat (500 tokens) | graphify + 1 chat | 80% |

---

## 11. Code Review Workflow

### ❌ Inefficient
```
Chat: "Here's my code [paste 50 lines], review it"
      → Context: 50 lines + review response
```

### ✅ Efficient
```bash
# 1. Self-review with grep/read
grep -n "TODO\|FIXME\|console.log" college-mgmt/app/...

# 2. Check for patterns
grep -n "return\|throw" new-file.php | wc -l

# 3. Only chat if needed
"I implemented [feature]. Should I handle [edge case]?
Here's my code: [minimal snippet]"
```

**Impact:** 60% less context

---

## 12. Measurement: Track Your Token Usage

### Create a Simple Log
```bash
# Track token usage per session
echo "Session: Feature X | Messages: 25 | Est. tokens: 8,000" >> ~/token-log.txt
```

### Estimate Tokens
**Quick formula:**
- Per message (average): 200 tokens
- Per file read (1K lines): 2,000 tokens
- Per code block: 100-500 tokens

**Example:**
```
Session = 20 messages (4,000) 
        + 2 files read (4,000)
        + 5 code blocks (1,500)
        = ~9,500 tokens
```

### Optimization Target
```
Before: 30 messages/session → ~6,000 tokens
After:  10 messages/session → ~2,000 tokens
Result: 67% reduction
```

---

## 13. Project-Specific Optimization

### For College Management System

**Token-expensive operations:**
1. ❌ Reading full `web.php` (670+ lines) = 1,400 tokens
2. ❌ Listing all controllers (25+ files) = 500 tokens
3. ❌ Explaining authentication flow = 400 tokens

**Token-efficient alternatives:**
1. ✅ `grep -n "Route::get.*student" routes/web.php` = 10 tokens
2. ✅ `ls app/Http/Controllers/Student/` = 5 tokens
3. ✅ `graphify query "How does auth relate to roles?"` = 0 tokens

### Use CLAUDE.md as Reference
Instead of explaining repeatedly, create a `.claudeignore` or point to docs:

```bash
# Instead of explaining architecture every time:
"See CLAUDE.md for overview. I'm implementing [specific thing]."
```

**Savings: 200 tokens per explanation**

---

## 14. Conversation Patterns That Waste Tokens

### ❌ Anti-Patterns (Avoid These)

**1. Asking "Does this look good?"**
```
❌ "Here's my 100-line implementation. Does it look good?
   Any improvements?"
   → Claude must re-read entire context to answer
```

**2. Long back-and-forth clarifications**
```
❌ Chat 1: "Build a feature"
   Chat 2: "What's the schema?"
   Chat 3: "Can you use this pattern?"
   Chat 4: "Here's the fixed version"
   (repeated context X 4)
```

**3. Explaining code multiple times**
```
❌ "Here's my controller... here's how it works... 
   the flow is... then we..."
   (context repeated in explanation)
```

---

## 15. Optimal Workflow for This Project

### Session Structure (Minimal Tokens)

**Session: Add New Feature**

**Step 1 (5 min): Preparation**
```bash
# Get architecture context ONCE
graphify query "How do [similar features] work?"

# Save result in notepad
# Grep for relevant code
grep -n "class SimilarController" app/...
```

**Step 2 (5 min): Design in Chat**
```
Graphify shows: [paste relevant query result]

Here's my design:
- Model: [name]
- Controller: [name]
- Routes: [list]

Does this fit the pattern?
```
*One message with decision*

**Step 3 (10 min): Implementation**
```
Now implementing. No chat until done.

(Build locally, test)
```

**Step 4 (5 min): Code review**
```
Implemented. Does this [specific question] look right?
Here's the key part: [snippet only]
```
*Single focused question*

**Total chat: ~300 tokens (not 3,000)**

---

## Checklist: Before Every Chat Message

- [ ] Graphify query will be faster? (Run it instead)
- [ ] Grepping for code will answer this? (Do it first)
- [ ] Do I need to paste the entire file? (Use specific lines)
- [ ] Have I asked this before in this chat? (Reference previous message)
- [ ] Is my request specific or vague? (Make it specific)
- [ ] Am I repeating context? (Use @mention or reference)
- [ ] Could this be a separate short chat? (Start new session)

---

## Real Numbers: Estimated Token Savings

### Scenario: Build PMC Elective Management Feature

**Old Way (Without Optimization):**
```
Chat 1: Explain current student model (300 tokens)
Chat 2: Explain elective concept (250 tokens)
Chat 3: Show existing patterns (400 tokens)
Chat 4: Code review (300 tokens)
Chat 5: Fix bugs (400 tokens)
Total: ~1,650 tokens
```

**New Way (With This Guide):**
```
Setup:
- graphify query "How do student enrollments work?" (0 tokens)
- grep elective references in code (0 tokens)

Chat 1: "Design: [uses graphify + grep findings]" (200 tokens)
Chat 2: "Implement: [minimal question about edge case]" (100 tokens)
Chat 3: "Final review: [specific snippet]" (100 tokens)
Total: ~400 tokens
```

**Savings: 76% reduction!**

---

## Summary: Quick Wins

| Action | Token Savings | Effort |
|--------|---------------|--------|
| Use /fast mode | 10% | ⭐ |
| Use graphify for architecture | 50% | ⭐⭐ |
| Grep instead of asking | 80% | ⭐⭐ |
| Specific requests only | 40% | ⭐⭐ |
| Single-focus chats | 30% | ⭐ |
| Only include relevant code | 60% | ⭐⭐ |
| Clear history between tasks | 20% | ⭐ |

**Combined effect: 60-80% reduction**

---

## Implementation Plan

### Week 1: Immediate Changes
- [ ] Enable /fast mode
- [ ] Install graphify (already done)
- [ ] Update CLAUDE.md with token guidelines
- [ ] Create a token tracking log

### Week 2: Behavioral Changes
- [ ] Use graphify before every architecture question
- [ ] Start using single-focus chats
- [ ] Practice specific, concise requests
- [ ] Clear history between features

### Week 3: Optimization
- [ ] Measure actual savings
- [ ] Identify your personal high-waste patterns
- [ ] Refine workflow based on real data

---

## For This Project Specifically

Given your college management system:

**Stop doing:**
```bash
# These waste tokens
- Reading entire web.php (use grep)
- Listing all controllers (use ls)
- Explaining student flow repeatedly (use graphify)
- Back-and-forth clarifications (be specific)
```

**Start doing:**
```bash
# These save tokens
- grep "Route::get.*student" routes/web.php
- graphify query "What's the student enrollment flow?"
- Specific: "Add ResumUpload validation for file size"
- Single message: Design + code snippet + 1 question
```

---

**Target:** Reduce monthly token usage by 70% while maintaining code quality with graphify context.
