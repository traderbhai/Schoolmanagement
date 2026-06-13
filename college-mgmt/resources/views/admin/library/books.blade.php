@extends('layouts.admin')
@section('title', 'Book Catalog')
@section('page-title', 'Book Catalog')
@section('content')
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="flex-grow-1">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search title, author, ISBN…" value="{{ request('q') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBookModal"><i class="bi bi-plus-lg me-1"></i>Add Book</button>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Total</th><th>Available</th><th></th></tr></thead>
            <tbody>
                @forelse($books as $book)
                <tr>
                    <td><a href="{{ route('admin.library.books.show', $book) }}" class="text-decoration-none">{{ Str::limit($book->title, 50) }}</a></td>
                    <td><small>{{ $book->author }}</small></td>
                    <td><small class="text-muted">{{ $book->isbn ?? '—' }}</small></td>
                    <td><small>{{ $book->category ?? '—' }}</small></td>
                    <td>{{ $book->total_copies }}</td>
                    <td><span class="badge bg-{{ $book->available_copies > 0 ? 'success' : 'danger' }}">{{ $book->available_copies }}</span></td>
                    <td><a href="{{ route('admin.library.books.show', $book) }}" class="btn btn-xs btn-outline-secondary">View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No books found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($books->hasPages())<div class="card-footer bg-white">{{ $books->links() }}</div>@endif
</div>
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST" action="{{ route('admin.library.books.store') }}">@csrf
            <div class="modal-header"><h6 class="modal-title">Add Book to Catalog</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-8"><label class="form-label small">Title *</label><input type="text" name="title" class="form-control form-control-sm" required></div>
                    <div class="col-md-4"><label class="form-label small">ISBN</label><input type="text" name="isbn" class="form-control form-control-sm" placeholder="978-…"></div>
                    <div class="col-md-6"><label class="form-label small">Author *</label><input type="text" name="author" class="form-control form-control-sm" required></div>
                    <div class="col-md-6"><label class="form-label small">Publisher</label><input type="text" name="publisher" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><label class="form-label small">Edition</label><input type="text" name="edition" class="form-control form-control-sm" placeholder="3rd"></div>
                    <div class="col-md-3"><label class="form-label small">Year</label><input type="number" name="year_of_publication" class="form-control form-control-sm" placeholder="{{ date('Y') }}"></div>
                    <div class="col-md-3"><label class="form-label small">Category</label><input type="text" name="category" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><label class="form-label small">Copies</label><input type="number" name="total_copies" class="form-control form-control-sm" value="1" min="1"></div>
                    <div class="col-md-6"><label class="form-label small">Shelf Location</label><input type="text" name="location" class="form-control form-control-sm" placeholder="A-101, Rack 3"></div>
                    <div class="col-md-6"><label class="form-label small">Language</label><input type="text" name="language" class="form-control form-control-sm" value="English"></div>
                    <div class="col-12"><label class="form-label small">Description</label><textarea name="description" class="form-control form-control-sm" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Add Book</button></div>
        </form>
    </div></div>
</div>
@endsection
