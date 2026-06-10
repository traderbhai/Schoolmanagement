# Claude Prompt Caching Implementation Guide

## Overview

Prompt caching reduces Claude API costs by ~**90% on cached input tokens**. This guide shows how to implement it in this Laravel college management system.

## How It Works

### Cache Mechanics

1. **First Request**: All tokens are charged at full rate
   ```
   System context (5,000 tokens) = 5,000 tokens charged
   User message (100 tokens) = 100 tokens charged
   Total = 5,100 tokens
   Cost = $0.025 (at $0.005 per 1K input tokens)
   ```

2. **Subsequent Requests** (same system context): Cached context is reused
   ```
   System context (5,000 tokens) = cached, 0 cost
   User message (100 tokens) = 100 tokens charged
   Total = 100 tokens charged
   Cost = $0.0005
   Savings = 98% reduction on that portion
   ```

### Cache TTL (Time-to-Live)

- **Default**: 5 minutes (ephemeral)
- **Optional**: Up to 1 hour with extended TTL
- Cache automatically invalidates if ANY byte in the cached prefix changes

## Setup Instructions

### 1. Install Dependencies

```bash
cd college-mgmt
composer require anthropic-ai/sdk
```

### 2. Configure Environment

Update `.env`:
```
ANTHROPIC_API_KEY=sk-ant-v1-your-key-here
```

### 3. Service Configuration

The `ClaudeService` is already configured in `/app/Services/ClaudeService.php` with:
- System prompt caching for consistent context
- Program curriculum caching (reused across multiple requests)
- Admission rubric caching (used for batch evaluations)

## Use Cases in This Project

### Use Case 1: Student Performance Analysis

**Scenario**: Dean analyzes 100 students' academic standing

**Benefits**:
- System context (academic guidelines) cached for 5 minutes
- Each student analysis: ~90% cost reduction
- Processing 100 students: First = $0.05, Next 99 = $0.001 each

**Implementation**:
```php
$student = Student::find($studentId);
$analysis = $this->claudeService->analyzeStudentPerformance(
    $studentData,
    'Is this student at academic risk?'
);
```

**Cache Breakdown**:
```
Request 1 (Student A):
├─ System context (cached)  → 3000 tokens @ full rate
├─ Student data              → 200 tokens @ full rate
└─ Total: 3200 tokens

Request 2 (Student B):
├─ System context (CACHED)   → 3000 tokens @ 90% discount
├─ Student data              → 200 tokens @ full rate
└─ Total: 320 tokens to bill, 2800 tokens saved
```

### Use Case 2: Admission Application Evaluation

**Scenario**: Admission head evaluates 50 applications

**Benefits**:
- Evaluation rubric cached (detailed criteria)
- Each subsequent application uses cached rubric
- 50 evaluations: First = $0.10, Next 49 = $0.001 each

**Implementation**:
```php
$applicant = Applicant::find($applicantId);
$evaluation = $this->claudeService->evaluateAdmissionApplication($applicantData);

return [
    'evaluation' => $evaluation['evaluation'],
    'cache_hit' => $evaluation['cache_hit'],
    'tokens_saved' => $evaluation['cache_read_tokens'] * 0.9,
];
```

**Cost Savings Example**:
```
Evaluating 50 applicants:
First applicant:  4500 tokens @ $0.005/1K = $0.0225
Next 49:          500 tokens each @ $0.0005/1K = $0.00245 each
Total 50:         $0.0225 + (49 × $0.00245) = $0.142
Without caching:  4500 × 50 × $0.005/1K = $1.125
Savings:          87%
```

### Use Case 3: Curriculum Planning (PMC Routine)

**Scenario**: Program chair reviews curriculum throughout semester

**Benefits**:
- Full curriculum data cached (subjects, credits, prerequisites)
- Can ask multiple curriculum questions using same cached context
- All subsequent queries: ~90% cost reduction

**Implementation**:
```php
// First query: Load and cache curriculum
$recommendation = $this->claudeService->generateCurriculumRecommendation(
    $programId,
    'Should we add a new elective on Machine Learning?'
);

// Second query: Reuses cached curriculum
$recommendation = $this->claudeService->generateCurriculumRecommendation(
    $programId,
    'What are the prerequisites for the advanced algorithms course?'
);
// Cache hit! 90% cheaper
```

## Cache Control Placement Strategy

### Critical Rule: Cache Control Order

```php
// ✅ CORRECT: System + large stable context first (gets cached)
'system' => [
    [
        'type' => 'text',
        'text' => $stableLargeContext,
        'cache_control' => ['type' => 'ephemeral'],
    ],
],
'messages' => [
    [
        'role' => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => $largeStableData,
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => $variableQuery,  // No cache_control here
            ],
        ],
    ],
]

// ❌ WRONG: Would cache everything, including the variable query
'messages' => [
    [
        'role' => 'user',
        'content' => $variableQuery,
    ],
    [
        'type' => 'text',
        'text' => $largeStableData,
        'cache_control' => ['type' => 'ephemeral'],
    ],
]
```

## Silent Cache Invalidators to Avoid

These will silently break caching because they change on each request:

```php
// ❌ DON'T: Use current timestamp
'text' => 'Today is ' . now()->toDateString(),

// ❌ DON'T: Use random data
'text' => 'Request ID: ' . Str::uuid(),

// ❌ DON'T: Use non-deterministic data
'text' => json_encode($data),  // If array keys reorder

// ✅ DO: Ensure deterministic, stable content
'text' => 'Evaluate based on these fixed criteria...'
```

## Monitoring Cache Hits

The `ClaudeService` returns cache information in responses:

```php
$result = $this->claudeService->evaluateAdmissionApplication($data);

echo $result['cache_hit'];              // true/false
echo $result['input_tokens'];           // Tokens actually billed
echo $result['cache_read_tokens'];      // Tokens served from cache
echo $result['cache_creation_tokens'];  // Tokens added to cache
```

**Track in logs**:
```php
if ($result['cache_hit']) {
    Log::info('Cache hit! Saved tokens: ' . $result['cache_read_tokens']);
}
```

## TTL Strategy for This Project

### 5-Minute (Default) Cache

Use for:
- Student analysis batches (analysis sessions last < 5 min)
- Real-time academic advisor queries
- Dashboard generation

```php
'cache_control' => ['type' => 'ephemeral']  // Default 5 min
```

### 1-Hour Cache (Longer TTL)

Use for:
- Admission evaluation rubrics (unchanged all day)
- Curriculum context (stable throughout week)
- Assessment guidelines

```php
// Extend to 1 hour for stable, large content
'cache_control' => ['type' => 'ephemeral', 'ttl_seconds' => 3600]
```

## API Key Security

### Never Commit Keys

```bash
# .env is in .gitignore (already configured)
# Add to .env (locally only):
ANTHROPIC_API_KEY=sk-ant-v1-xxxx

# Never add to .env.example
# Never commit to git
```

### Production Deployment

Use environment variable injection:
```bash
# On Vercel/Railway/Heroku:
ANTHROPIC_API_KEY=sk-ant-v1-xxxx  # Set via dashboard
```

## Rate Limiting & Quotas

Check your Anthropic plan:
- **Free tier**: Limited requests
- **Pro tier**: Higher rate limits
- **Enterprise**: Custom limits

Monitor usage:
```php
// Log every request
Log::info('Claude API call', [
    'tokens_used' => $response->usage->input_tokens,
    'cache_hit' => $response->usage->cache_read_input_tokens > 0,
]);
```

## Troubleshooting

### Cache Not Working?

Check these:

1. **Render order**: System prompt before user messages
2. **Byte-for-byte stability**: Same exact text, same formatting
3. **TTL**: Default 5 minutes — cache may have expired
4. **Content type**: Only `text` blocks support caching

### API Errors

```php
try {
    $response = $this->client->messages->create([...]);
} catch (\Exception $e) {
    Log::error('Claude API error: ' . $e->getMessage());
    return 'Analysis unavailable';
}
```

## Cost Savings Calculation

### Before Caching (without optimization)

```
Task: Evaluate 100 applicants with detailed rubric
Rubric size: 5,000 tokens
Per applicant: 500 tokens

Cost = (100 × (5000 + 500)) / 1000 × $0.003 = $1.65
```

### After Caching (with prompt caching)

```
First applicant:  5500 tokens = $0.0165
Next 99:          500 tokens = $0.0015 each
Total: $0.0165 + (99 × $0.0015) = $0.1650
Savings: 90%
```

## Next Steps

1. **Replace API key** in `.env` with your actual Anthropic API key
2. **Test the endpoints**:
   ```bash
   curl -X GET http://localhost:8000/api/claude/student/1/analyze \
     -H "Authorization: Bearer {token}"
   ```
3. **Integrate** with your existing controllers
4. **Monitor** cache hits via logs
5. **Optimize** based on your usage patterns

## Real-World Example: Full Academic Analysis Workflow

```php
// PMC analyzing entire batch (50 students)
$batch = Batch::find($batchId);
$students = $batch->students()->with(['enrollments', 'examResults'])->get();

foreach ($students as $student) {
    // First student: cache charged
    // Students 2-50: use cache (90% savings per student)
    $analysis = $this->claudeService->analyzeStudentPerformance(
        [
            'name' => $student->user->name,
            'cgpa' => $student->cgpa,
            'attendance' => $student->attendance_percentage,
            'arrears' => $student->pending_arrears,
        ],
        'Provide academic risk assessment'
    );
    
    // Process analysis (save to database, send alerts, etc.)
}

// Cost for 50 students: ~$0.08 instead of $0.75 (90% savings)
```

---

**Need help?** Check `/app/Services/ClaudeService.php` for implementation details.
