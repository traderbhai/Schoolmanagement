@extends('layouts.student')
@section('title', 'My Resume')
@section('page-title', 'Resume Builder')

@section('content')
<div class="container-fluid py-3" style="max-width:860px">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-warning">{{ session('error') }}</div>
    @endif
    @unless($canEditResume)
        <div class="alert alert-secondary">
            <i class="bi bi-lock me-1"></i>Resume updates are locked because your student profile is not active. Your saved resume remains visible for history.
        </div>
    @endunless
    <div class="alert alert-info border-0 shadow-sm small">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Build the resume CMC and placement teams will use for shortlisting support.</div>
        <div class="text-muted">Add your headline, skills, projects, internships, certifications, and profile links. Empty sections are normal at the start; save updates as your placement profile improves.</div>
    </div>

    <form method="POST" action="{{ route('student.resume.save') }}" id="resumeForm">
        @csrf
        <fieldset @disabled(!$canEditResume)>

        {{-- Header / Headline --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold"><i class="bi bi-person-vcard me-2"></i>Basic Info</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Headline</label>
                        <input aria-label="Headline" type="text" name="headline" class="form-control" value="{{ old('headline', $resume->headline) }}"
                               placeholder="e.g. Final Year B.Tech Computer Science">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CGPA (auto)</label>
                        <input aria-label="Current CGPA" type="text" class="form-control" value="{{ $cgpa ?? '-' }}" disabled>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Objective / Summary</label>
                        <textarea aria-label="Professional summary" name="objective" rows="3" class="form-control"
                                  placeholder="Brief professional summary...">{{ old('objective', $resume->objective) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Links --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold"><i class="bi bi-link-45deg me-2"></i>Links</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">LinkedIn</label>
                    <input aria-label="Linkedin Url" type="url" name="linkedin_url" class="form-control form-control-sm"
                           value="{{ old('linkedin_url', $resume->linkedin_url) }}" placeholder="https://linkedin.com/in/...">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">GitHub</label>
                    <input aria-label="Github Url" type="url" name="github_url" class="form-control form-control-sm"
                           value="{{ old('github_url', $resume->github_url) }}" placeholder="https://github.com/...">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Portfolio</label>
                    <input aria-label="Portfolio Url" type="url" name="portfolio_url" class="form-control form-control-sm"
                           value="{{ old('portfolio_url', $resume->portfolio_url) }}" placeholder="https://...">
                </div>
            </div>
        </div>

        {{-- Skills --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold"><i class="bi bi-tools me-2"></i>Skills</div>
            <div class="card-body">
                <input aria-label="Skills" type="text" name="skills" class="form-control"
                       value="{{ old('skills', implode(', ', $resume->skills ?? [])) }}"
                       placeholder="Python, PHP, MySQL, React (comma-separated)">
                <div class="form-text">Comma-separated list of skills.</div>
            </div>
        </div>

        {{-- Languages --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold"><i class="bi bi-translate me-2"></i>Languages</div>
            <div class="card-body">
                <input aria-label="Languages" type="text" name="languages" class="form-control"
                       value="{{ old('languages', implode(', ', $resume->languages ?? [])) }}"
                       placeholder="English, Hindi, Marathi (comma-separated)">
            </div>
        </div>

        {{-- Projects --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-code-square me-2"></i>Projects</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProject()">+ Add</button>
            </div>
            <div class="card-body" id="projectsContainer">
                @php $projects = $resume->projects ?? []; @endphp
                @forelse($projects as $i => $p)
                <div class="border rounded p-3 mb-2 project-item">
                    <div class="row g-2">
                        <div class="col-md-6"><input aria-label="Project title" type="text" name="projects[{{ $i }}][title]" class="form-control form-control-sm" placeholder="Project title" value="{{ $p['title'] ?? '' }}"></div>
                        <div class="col-md-6"><input aria-label="Technologies used" type="text" name="projects[{{ $i }}][tech]" class="form-control form-control-sm" placeholder="Technologies used" value="{{ $p['tech'] ?? '' }}"></div>
                        <div class="col-12"><textarea aria-label="Brief description" name="projects[{{ $i }}][description]" class="form-control form-control-sm" rows="2" placeholder="Brief description">{{ $p['description'] ?? '' }}</textarea></div>
                        <div class="col-md-6"><input aria-label="Project URL" type="url" name="projects[{{ $i }}][url]" class="form-control form-control-sm" placeholder="Project URL (optional)" value="{{ $p['url'] ?? '' }}"></div>
                        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.project-item').remove()">Remove</button></div>
                    </div>
                </div>
                @empty
                <div class="text-muted small" id="noProjects">
                    <div class="fw-semibold text-dark mb-1">No projects added yet</div>
                    <div>Add academic, capstone, lab, competition, or self-learning projects that show the skills you want recruiters to notice.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Experience --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-briefcase me-2"></i>Experience / Internships</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addExperience()">+ Add</button>
            </div>
            <div class="card-body" id="experienceContainer">
                @php $experiences = $resume->experience ?? []; @endphp
                @forelse($experiences as $i => $e)
                <div class="border rounded p-3 mb-2 exp-item">
                    <div class="row g-2">
                        <div class="col-md-6"><input aria-label="Company / Organisation" type="text" name="experience[{{ $i }}][company]" class="form-control form-control-sm" placeholder="Company / Organisation" value="{{ $e['company'] ?? '' }}"></div>
                        <div class="col-md-6"><input aria-label="Role / Position" type="text" name="experience[{{ $i }}][role]" class="form-control form-control-sm" placeholder="Role / Position" value="{{ $e['role'] ?? '' }}"></div>
                        <div class="col-md-3"><input aria-label="Experience" type="month" name="experience[{{ $i }}][from]" class="form-control form-control-sm" value="{{ $e['from'] ?? '' }}"></div>
                        <div class="col-md-3"><input aria-label="Present" type="month" name="experience[{{ $i }}][to]" class="form-control form-control-sm" value="{{ $e['to'] ?? '' }}" placeholder="Present"></div>
                        <div class="col-12"><textarea aria-label="Experience description" name="experience[{{ $i }}][description]" class="form-control form-control-sm" rows="2" placeholder="What you did...">{{ $e['description'] ?? '' }}</textarea></div>
                        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.exp-item').remove()">Remove</button></div>
                    </div>
                </div>
                @empty
                <div class="text-muted small" id="noExp">
                    <div class="fw-semibold text-dark mb-1">No internship or work experience added yet</div>
                    <div>Add internships, live projects, volunteering, campus roles, or training experience when available. Freshers can leave this empty and strengthen projects/certifications instead.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Certifications --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-patch-check me-2"></i>Certifications</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCert()">+ Add</button>
            </div>
            <div class="card-body" id="certContainer">
                @php $certs = $resume->certifications ?? []; @endphp
                @forelse($certs as $i => $c)
                <div class="border rounded p-2 mb-2 cert-item d-flex gap-2 align-items-center">
                    <input aria-label="Certification name" type="text" name="certifications[{{ $i }}][name]" class="form-control form-control-sm" placeholder="Certification name" value="{{ $c['name'] ?? '' }}">
                    <input aria-label="Issuer" type="text" name="certifications[{{ $i }}][issuer]" class="form-control form-control-sm" placeholder="Issuer" value="{{ $c['issuer'] ?? '' }}">
                    <input aria-label="Certifications" type="month" name="certifications[{{ $i }}][date]" class="form-control form-control-sm" value="{{ $c['date'] ?? '' }}">
                    <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="this.closest('.cert-item').remove()">x</button>
                </div>
                @empty
                <div class="text-muted small" id="noCerts">
                    <div class="fw-semibold text-dark mb-1">No certifications added yet</div>
                    <div>Add verified courses, technical certificates, workshops, or industry badges that support your placement profile.</div>
                </div>
                @endforelse
            </div>
        </div>

        </fieldset>

        @if($canEditResume)
            <button type="submit" class="btn btn-primary px-4">Save Resume</button>
        @else
            <span class="badge bg-secondary">Active students only</span>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script>
let projectCount = {{ count($resume->projects ?? []) }};
let expCount = {{ count($resume->experience ?? []) }};
let certCount = {{ count($resume->certifications ?? []) }};

function addProject() {
    document.getElementById('noProjects')?.remove();
    const html = `<div class="border rounded p-3 mb-2 project-item"><div class="row g-2">
        <div class="col-md-6"><input aria-label="Project title" type="text" name="projects[${projectCount}][title]" class="form-control form-control-sm" placeholder="Project title"></div>
        <div class="col-md-6"><input aria-label="Technologies used" type="text" name="projects[${projectCount}][tech]" class="form-control form-control-sm" placeholder="Technologies used"></div>
        <div class="col-12"><textarea aria-label="Brief description" name="projects[${projectCount}][description]" class="form-control form-control-sm" rows="2" placeholder="Brief description"></textarea></div>
        <div class="col-md-6"><input aria-label="Project URL" type="url" name="projects[${projectCount}][url]" class="form-control form-control-sm" placeholder="Project URL (optional)"></div>
        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.project-item').remove()">Remove</button></div>
    </div></div>`;
    document.getElementById('projectsContainer').insertAdjacentHTML('beforeend', html);
    projectCount++;
}

function addExperience() {
    document.getElementById('noExp')?.remove();
    const html = `<div class="border rounded p-3 mb-2 exp-item"><div class="row g-2">
        <div class="col-md-6"><input aria-label="Company / Organisation" type="text" name="experience[${expCount}][company]" class="form-control form-control-sm" placeholder="Company / Organisation"></div>
        <div class="col-md-6"><input aria-label="Role / Position" type="text" name="experience[${expCount}][role]" class="form-control form-control-sm" placeholder="Role / Position"></div>
        <div class="col-md-3"><input aria-label="Experience" type="month" name="experience[${expCount}][from]" class="form-control form-control-sm"></div>
        <div class="col-md-3"><input aria-label="Present" type="month" name="experience[${expCount}][to]" class="form-control form-control-sm" placeholder="Present"></div>
        <div class="col-12"><textarea aria-label="Experience description" name="experience[${expCount}][description]" class="form-control form-control-sm" rows="2" placeholder="What you did..."></textarea></div>
        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.exp-item').remove()">Remove</button></div>
    </div></div>`;
    document.getElementById('experienceContainer').insertAdjacentHTML('beforeend', html);
    expCount++;
}

function addCert() {
    document.getElementById('noCerts')?.remove();
    const html = `<div class="border rounded p-2 mb-2 cert-item d-flex gap-2 align-items-center">
        <input aria-label="Certification name" type="text" name="certifications[${certCount}][name]" class="form-control form-control-sm" placeholder="Certification name">
        <input aria-label="Issuer" type="text" name="certifications[${certCount}][issuer]" class="form-control form-control-sm" placeholder="Issuer">
        <input aria-label="Certifications" type="month" name="certifications[${certCount}][date]" class="form-control form-control-sm">
        <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="this.closest('.cert-item').remove()">x</button>
    </div>`;
    document.getElementById('certContainer').insertAdjacentHTML('beforeend', html);
    certCount++;
}
</script>
@endpush
