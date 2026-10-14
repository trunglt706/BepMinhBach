@php use App\Support\Format; @endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checklist · {{ $batch->name }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/be-vietnam-pro/font.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body { background: #fff; color: #000; }
        .print-sheet { max-width: 800px; margin: 0 auto; }
        .box { display: inline-block; width: 18px; height: 18px; border: 1.5px solid #000; vertical-align: middle; }
        .checklist td, .checklist th { padding: .4rem .5rem; vertical-align: top; border-color: #555 !important; }
        .checklist th { background: #f2f2f2 !important; }
        .write-line { border-bottom: 1px solid #555; min-width: 90px; display: inline-block; }
        /* Xem trên điện thoại: bảng cuộn ngang trong khung thay vì làm cả trang tràn ra; bản in A4 không bị ảnh hưởng. */
        @media screen and (max-width: 575.98px) {
            .checklist-scroll { overflow-x: auto; margin-bottom: 1rem; }
            .checklist-scroll .checklist { min-width: 540px; margin-bottom: 0; }
        }
    </style>
</head>
<body>
<div class="print-sheet p-3">
    <div class="d-print-none alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span>Bản in checklist khổ A4 (không có menu). Có thể lưu PDF từ hộp thoại in của trình duyệt.</span>
        <span class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-print>In checklist</button>
            <a href="{{ route('batches.show', $batch) }}" class="btn btn-outline-secondary">Quay lại</a>
        </span>
    </div>

    <h1 class="h4 mb-1">Checklist: {{ $batch->name }}</h1>
    <p class="mb-1">
        <strong>Thời gian:</strong> {{ Format::date($batch->starts_on) }}
        @if($batch->location_text) · <strong>Địa điểm:</strong> {{ $batch->location_text }} @endif
    </p>
    <p class="mb-1"><strong>Điều phối:</strong> {{ $batch->coordinator?->name }} · <strong>Suất dự kiến:</strong> {{ Format::number($batch->expected_servings) }}
        · <strong>Trạng thái:</strong> {{ $batch->status->label() }}</p>
    <p class="small mb-3">In lúc {{ Format::dateTime(now()) }}. Đánh dấu ☐ khi xong và ghi chú lại để cập nhật lên hệ thống sau.</p>

    <h2 class="h5 mt-3">Công việc ({{ $batch->tasks->count() }})</h2>
    @if($batch->tasks->isEmpty())
        <p>Chưa có công việc.</p>
    @else
        <div class="checklist-scroll" tabindex="0" role="region" aria-label="Bảng checklist (vuốt ngang để xem hết)">
        <table class="table table-bordered checklist">
            <thead><tr><th style="width:36px">Xong</th><th>Công việc</th><th style="width:22%">Người phụ trách</th><th style="width:18%">Hạn</th></tr></thead>
            <tbody>
            @foreach($batch->tasks as $task)
                <tr>
                    <td class="text-center">@if($task->status === App\Enums\TaskStatus::Done)<span aria-label="Đã xong">☑</span>@else<span class="box" aria-label="Chưa xong"></span>@endif</td>
                    <td>
                        <strong>{{ $task->title }}</strong>
                        @if($task->description)<div class="small">{{ $task->description }}</div>@endif
                    </td>
                    <td>{{ $task->assignee?->name ?? '' }}</td>
                    <td>{{ $task->due_at ? Format::dateTime($task->due_at) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif

    <h2 class="h5 mt-4">Nguyên liệu ({{ $batch->ingredients->count() }})</h2>
    @if($batch->ingredients->isEmpty())
        <p>Chưa có nguyên liệu.</p>
    @else
        <div class="checklist-scroll" tabindex="0" role="region" aria-label="Bảng checklist (vuốt ngang để xem hết)">
        <table class="table table-bordered checklist">
            <thead><tr><th style="width:36px">Đủ</th><th>Nguyên liệu</th><th style="width:20%">Dự kiến</th><th style="width:22%">Thực tế dùng</th><th style="width:20%">Ghi chú</th></tr></thead>
            <tbody>
            @foreach($batch->ingredients as $ingredient)
                <tr>
                    <td class="text-center"><span class="box" aria-label="Ô đánh dấu"></span></td>
                    <td><strong>{{ $ingredient->name }}</strong>@if($ingredient->needs_restock) <em>(đang báo thiếu)</em>@endif</td>
                    <td>{{ Format::quantity($ingredient->expected_quantity) }} {{ $ingredient->unit }}</td>
                    <td><span class="write-line">&nbsp;</span> {{ $ingredient->unit }}</td>
                    <td class="small">{{ $ingredient->notes }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif

    <h2 class="h5 mt-4">Kết quả (ghi tay)</h2>
    <p>Số suất đã chuẩn bị: <span class="write-line" style="min-width:140px">&nbsp;</span>
        &nbsp; Số suất đã phân phát: <span class="write-line" style="min-width:140px">&nbsp;</span></p>
    <p>Ghi chú: <span class="write-line" style="min-width:85%">&nbsp;</span></p>

    <p class="small mt-4 mb-0">Checklist chỉ hỗ trợ điều phối nội bộ, không thay thế quy trình hay chứng nhận an toàn thực phẩm.</p>
</div>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
