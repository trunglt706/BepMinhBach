@csrf
<x-input name="name" label="Tên khoản chi" :value="$expense->name" required maxlength="150" help="Ví dụ: Rau củ chợ sáng, Gas, Hộp đựng cơm" />
<x-input name="amount_vnd" label="Số tiền" :value="$expense->amount_vnd" required money maxlength="17" class="form-control-lg"
         help="Chỉ nhập số; dấu chấm phân cách hàng nghìn được thêm tự động." />
<x-input name="note" label="Ghi chú" :value="$expense->note" maxlength="500" help="Không bắt buộc. Không cần tải chứng từ." />
