<div class="container contenu_eleve" style="margin-top:60px">
    <ol class="breadcrumb">
        <li><i class="glyphicon glyphicon-open-file"></i> إرفاق الوثائق</li>
        <li class="active">الخطوة 4: تأكيد الإرفاق</li>
    </ol>
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-success">
            <div class="panel-heading"><h3 class="panel-title">تمت عملية الإرفاق بنجاح</h3></div>
            <div class="panel-body">
                <div class="alert alert-success text-center" style="font-size: 1.2em;">
                    <span class="glyphicon glyphicon-ok-circle" style="font-size: 2em; margin-bottom: 15px;"></span><br>
                    لقد تم إرفاق وثائقك بنجاح.
                </div>
                
                <div id="receipt-to-print">
                    <h4 class="text-center">وصل إرفاق الوثائق</h4>
                    <hr>
                    
                    <!-- ================================== -->
                    <!-- التعديل 2: إضافة بيانات المتعلم -->
                    <!-- ================================== -->
                    <?php if(isset($student_info)): ?>
                        <div class="well well-sm">
                            <p><strong>رقم الاستمارة:</strong> <?php echo htmlspecialchars($student_info['enpr']); ?></p>
                            <p><strong>الاسم واللقب:</strong> <?php echo htmlspecialchars($student_info['name']); ?></p>
                            <p><strong>تاريخ الميلاد:</strong> <?php echo htmlspecialchars($student_info['dns']); ?></p>
                        </div>
                    <?php endif; ?>
                    <!-- ================================== -->

                    <h4>الوثائق التي تم إرفاقها:</h4>
                    <ul class="list-group">
                        <?php if (isset($uploaded_files_display) && !empty($uploaded_files_display)): ?>
                            <?php foreach ($uploaded_files_display as $label => $url): ?>
                                <li class="list-group-item">
                                    <span class="glyphicon glyphicon-file"></span> <?php echo htmlspecialchars($label); ?>
                                    <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="btn btn-info btn-xs pull-left">معاينة</a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item">لم يتم إرفاق أي وثائق.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <hr>
                <p class="text-center">
                    <button type="button" onclick="printReceipt()" class="btn btn-primary btn-lg">
                        <span class="glyphicon glyphicon-print"></span> طباعة هذا الوصل
                    </button>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function printReceipt() {
    var printContents = document.getElementById('receipt-to-print').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = '<html><head><title>طباعة وصل</title><link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" xintegrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous"><style>body { direction: rtl; }</style></head><body>' + printContents + '</body></html>';
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
</script>