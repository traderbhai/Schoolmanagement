# Graphify-Driven Development Workflow

> **Goal:** Use graphify as a standard development tool to understand architecture, reduce token usage in AI chats, and maintain high code quality.

## Why Graphify?

Instead of describing code structure verbally (100+ tokens per explanation), graphify provides:
- **Visual architecture understanding** (instant, zero tokens)
- **Precise component relationships** (queryable, minimal tokens)
- **Impact analysis before refactoring** (identify cascade effects)
- **Onboarding new developers** (show, don't explain)

**Result:** Better code decisions with 50-70% fewer chat tokens.

---

## Workflow: Before Each Feature Sprint

### Phase 1: Analyze Current State (5 minutes)

1. **Run graphify to snapshot architecture:**
   ```bash
   cd college-mgmt
   graphify update .
   ```
   This updates the cache; no API cost, instant results.

2. **Query for sprint context:**
   ```bash
   # Ask about relevant architecture
   graphify query "What controllers handle student admission flows?"
   graphify query "Which models depend on ApprovalWorkflow?"
   graphify query "Show all routes in the program-chair namespace"
   ```

3. **Read the relevant community** from `graphify-out/GRAPH_REPORT.md`
   - Find "Communities" section
   - Identify which communities align with your feature
   - Note "God Nodes" (highly connected abstractions)

### Phase 2: Propose Feature Changes (minimal tokens)

**Instead of:** "I want to add a timetable builder. Here's the flow... here are the models... here's how it integrates..."

**Do this:** "I want to add a timetable builder. Graphify shows [paste relevant query result]. Here's my proposal: [code snippet]. Does this follow the pattern?"

**Savings:** ~70% token reduction because the AI already has the architectural context from graphify output.

### Phase 3: Implement with Architecture Awareness

Before writing code, ask graphify:
- "Does this model overlap with existing ones?"
- "Which services depend on this table?"
- "What's the simplest place to hook this feature?"

**Example:**
```bash
graphify query "What services interact with TimetableEntry?"
# Result shows: TimetableService, ExamCell, StudentController
# Decision: Hook into TimetableService to avoid duplication
```

### Phase 4: After Implementation, Re-Graph

```bash
graphify update .
```

Verify:
- New nodes are correctly positioned in communities
- No surprise new dependencies
- No import cycles introduced
- Cohesion of affected communities didn't drop

---

## Claude Chat Protocol: Using Graphify Results

### Before Each AI Interaction:

1. **Run a query to ground the question:**
   ```bash
   graphify query "How does [component] relate to [other component]?"
   ```

2. **Paste the result in your chat:**
   > "Graphify output: [paste query result here]"
   > Now, I want to [implement feature]. Here's my approach..."

3. **AI uses graphify context** instead of asking you to describe architecture

4. **Result:** AI makes better recommendations, fewer clarifying questions, fewer tokens

### Example Chat Opener

**❌ Without graphify (costs ~200 tokens):**
```
"I want to add elective management to the PMC portal. 
Let me describe the current structure...
- We have programs with subjects
- Students register for electives
- PMC sees registration counts
- Need to handle min/max constraints
..."
```

**✅ With graphify (costs ~30 tokens):**
```
Graphify shows: Program → ProgramSubject → StudentSubjectEnrollment

Query result:
- ProgramSubject already has `type` and `elective_group`
- StudentSubjectEnrollment exists with `enrollment_type`
- No existing elective registration window model

Plan: Add ElectiveRegistrationWindow model, hook into StudentController.
Does this fit the architecture?
```

**Savings: 170 tokens per question!**

---

## Graphify Commands Reference

### Update Graph (No API Cost)
```bash
# Fast update after code changes
graphify update .

# Full re-scan (slower, more accurate)
graphify .
```

### Query Architecture
```bash
# Basic relationship question
graphify query "What connects Model A to Model B?"

# Multi-step queries
graphify query "Show all paths from ApprovalWorkflow to OfferLetter"

# Service discovery
graphify query "Which services use the Enrollment model?"

# Risk assessment before refactoring
graphify query "If I remove AdmissionService, what breaks?"

# Impact analysis
graphify query "What happens if I change the Batch model structure?"
```

### View Community Details
```bash
# Open graph in browser for visual exploration
open graphify-out/graph.html

# Read the report (search for relevant communities)
cat graphify-out/GRAPH_REPORT.md | grep -A 10 "Community X"
```

---

## By Development Phase

### 1. Sprint Planning (Day 1)

```bash
# Understand what exists
graphify query "What controllers handle [domain]?"
graphify query "Which models are involved in [workflow]?"

# Assess effort
graphify query "How many services depend on [model]?"
# High count = risky changes, need careful design
```

**Use in chat:** "Per graphify, [model] has 15 dependencies. We need to design carefully to avoid cascade breaks."

### 2. Feature Design (Day 1-2)

```bash
# Identify reuse opportunities
graphify query "What patterns exist for [similar feature]?"

# Check for gaps
graphify query "Is there already a model for [concept]?"

# Assess placement
graphify query "Should this go in [Service A] or [Service B]?"
```

**Use in chat:** "Graphify shows ApplicantNotificationService uses a template pattern. I'll use the same pattern for [new feature]."

### 3. Implementation (Day 2-4)

```bash
# During coding, spot-check architectural impact
graphify query "Does my new [Model] fit naturally with existing [Models]?"

# After coding, verify no surprises
graphify update .

# Check new communities don't have low cohesion
cat graphify-out/GRAPH_REPORT.md | grep "Cohesion:"
```

**Use in chat:** "After implementation, graphify shows 2 new communities (cohesion 0.45, 0.38). Both healthy. No import cycles."

### 4. Code Review (Day 4-5)

```bash
# Check for smell: did we create isolated nodes?
graphify query "Are there any disconnected components in [my new code]?"

# Assess overall health
cat graphify-out/GRAPH_REPORT.md | tail -50  # Knowledge gaps, suggestions
```

**Use in chat:** "Graphify identifies 2 weakly-connected nodes in my code. I should refactor to improve cohesion."

---

## Sprint Examples

### PMC Timetable Sprint

**Before starting:**
```bash
graphify query "What models handle timetable scheduling?"
# Shows: TimetableEntry, TimetableSlot, Classroom, Teacher, Subject
# Shows: TimetableService already exists

graphify query "Which controllers create/modify TimetableEntry?"
# Shows: TimetableController, ExamCellController

graphify query "How do students see their timetable?"
# Shows: StudentController → TimetableService → TimetableEntry
```

**Chat:** "Graphify shows we already have TimetableService. I'll extend it rather than create a new service. Here's my design: [code]"

**After implementation:**
```bash
graphify update .

# Verify new communities are well-connected
graphify query "Do my new timetable classes integrate cleanly?"

# Check no breaking changes
graphify query "Are there new isolated nodes?"
```

**Chat:** "New TimetableBuilder service integrates with TimetableService (15 shared dependencies). No cycles. Ready for review."

---

## Git Workflow: Graphify in CI/CD

### Before Committing

```bash
# Always update graph
graphify update .

# Check for issues
if graphify-out/GRAPH_REPORT.md contains "import cycles"; then
  echo "⚠️  Import cycles detected! Fix before commit."
  exit 1
fi

git add graphify-out/
git commit -m "Feature: [name] + graphify cache update"
```

### In Commit Message

```
Add elective management for PMC

Implementation:
- New ElectiveRegistrationWindow model
- Hook into StudentController via StudentSubjectEnrollment
- Graphify shows clean integration, no cycles

Architecture:
- Cohesion of affected communities: 0.42-0.56 (healthy)
- New nodes: 8, all connected
- No import cycles

https://claude.ai/code/session_xxx
```

---

## Token Savings: Real Numbers

### Scenario: Adding Marks Appeals Feature

**Without graphify:**
1. Chat: "I need to add marks appeals. Let me describe the marks system..." → **150 tokens**
2. AI: "Got it. Now, how does this relate to exam results?" → **50 tokens question cost**
3. Chat: "ExamResult has the marks, ApprovalWorkflow handles approvals..." → **120 tokens**
4. AI: "I see. Let me propose a design..." → **200 tokens response**
5. Back-and-forth clarifications × 3 → **1000+ tokens**

**Total: ~1500 tokens**

---

**With graphify:**
1. Run: `graphify query "How do exam results, approvals, and student marks connect?"`
2. Paste result into chat: **~50 tokens**
3. Chat: "Based on graphify, here's my design..." → **150 tokens**
4. AI: "Looks good, here's how to hook it in..." → **200 tokens response**
5. 1 clarification loop (not 3) → **200 tokens**

**Total: ~600 tokens**

**Savings: 60% token reduction!**

---

## Maintenance: Keep Graphify Updated

### Weekly (after significant commits)
```bash
graphify update .
git add graphify-out/
git commit -m "chore: update graphify cache"
```

### Before Major Refactors
```bash
# Get baseline
graphify .

# Make changes
# ... implement ...

# Compare
graphify .
# Review changes in GRAPH_REPORT.md
```

### At Sprint End
```bash
# Full regeneration with fresh data
graphify .

# Review health metrics
cat graphify-out/GRAPH_REPORT.md | grep -E "Cohesion:|God Nodes:|Import Cycles"
```

---

## Troubleshooting

### Graph Seems Stale?
```bash
graphify update .
```

### Relationship Not Showing Up?
```bash
graphify --mode deep .  # More aggressive extraction
```

### Want to See Specific Community?
```bash
graphify query "Show me everything in [component]"
# Or search GRAPH_REPORT.md directly
grep "ComponentName" graphify-out/GRAPH_REPORT.md
```

### HTML Won't Open?
```bash
# Check file exists
ls -lh graphify-out/graph.html

# Use Python server if needed
cd graphify-out && python -m http.server 8000
# Then open http://localhost:8000/graph.html
```

---

## Best Practices

1. **Query before designing** — Let graphify inform your architecture
2. **Paste results in chat** — Use AI's context window effectively
3. **Update after big changes** — Keep the graph fresh
4. **Commit graphify cache** — Track architecture evolution
5. **Check for cycles** — Before every PR
6. **Review cohesion** — After adding new communities

---

## Next: PMC Sprint Workflow

When implementing the PMC Portal (8 sprints of features):

1. **Day 1:** Run graphify, query about existing timetable/admission/approval infrastructure
2. **Day 1-2:** Design using graphify context; mention findings in chat
3. **Day 2-4:** Implement; run `graphify query` to spot-check decisions
4. **Day 5:** `graphify update`, review communities, commit cache
5. **Chat:** Minimal architecture discussion (graphify has it covered)

**Expected token savings:** 40-60% per sprint.

---

**Remember:** Every graphify query replaces 50-200 tokens of explanation. Use it ruthlessly.
