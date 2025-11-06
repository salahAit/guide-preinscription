<div class="container contenu_eleve" style="margin-top:60px">
     <ol class="breadcrumb">
        <li><i class="glyphicon glyphicon-open-file"></i> إرفاق الوثائق</li>
        <li class="active">الخطوة 3: إرفاق الملفات</li>
    </ol>
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-primary">
             <div class="panel-heading"><h3 class="panel-title">إرفاق ملف الوثائق الثبوتية (PDF)</h3></div>
            <div class="panel-body">
                <h4>مرحبًا بك، <?php echo htmlspecialchars($this->session->userdata('upload_student_name')); ?></h4>
                <p>رقم استمارتك هو: <strong><?php echo htmlspecialchars($this->session->userdata('upload_student_enpr')); ?></strong></p>
                <hr>
                
                <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                
                <div id="upload-errors" class="alert alert-danger" style="display: none;">
                    <strong>خطأ:</strong>
                    <ul id="error-list" style="padding-right: 20px;"></ul>
                </div>

                <?php echo form_open_multipart('upload_documents/do_upload', ['id' => 'uploadForm']);?>

                    <?php if (isset($is_returning) && $is_returning): ?>
                        <!-- ======================= -->
                        <!-- الحالة 1: متعلم قديم -->
                        <!-- ======================= -->
                        <div class="alert alert-info"><strong>ملاحظة:</strong> بما أنك متعلم قديم، يطلب منك فقط إرفاق **وصل الدفع (إجباري)**.</div>
                        <div class="form-group"><label for="doc_payment">1. وصل الدفع <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_payment" name="doc_payment" class="form-control" accept="application/pdf" required /></div>

                    <?php elseif (isset($is_reeducation) && $is_reeducation): ?>
                        <!-- ======================= -->
                        <!-- الحالة 2: إعادة التربية (FRAIS=1500) -->
                        <!-- ======================= -->
                        <div class="alert alert-warning"><strong>ملاحظة (إعادة التربية):</strong> يطلب منك إرفاق **شهادة الميلاد (إجبارية)**. باقي الوثائق اختيارية.</div>
                        <div class="form-group"><label for="doc_payment">1. وصل الدفع (اختياري)</label><input type="file" id="doc_payment" name="doc_payment" class="form-control" accept="application/pdf" /></div>
                        <div class="form-group"><label for="doc_birth">2. شهادة الميلاد <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_birth" name="doc_birth" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_photo">3. الصورة الشمسية (اختياري)</label><input type="file" id="doc_photo" name="doc_photo" class="form-control" accept="application/pdf" /></div>
                        <div class="form-group"><label for="doc_statement">4. التصريح الشرفي (اختياري)</label><input type="file" id="doc_statement" name="doc_statement" class="form-control" accept="application/pdf" /></div>
                        <div class="form-group"><label for="doc_school">5. شهادة مدرسية أو شهادة التحرر من محو الأمية (اختياري)</label><input type="file" id="doc_school" name="doc_school" class="form-control" accept="application/pdf" /></div>
                        <div class="form-group"><label for="doc_grades">6. كشف النقاط (اختياري)</label><input type="file" id="doc_grades" name="doc_grades" class="form-control" accept="application/pdf" /></div>
                
                <?php else: ?>
                    <!-- ======================= -->
                    <!-- الحالة 3: متعلم جديد (عادي أو ابن عامل) -->
                    <!-- ======================= -->
                    <?php if (isset($requires_extra_docs) && $requires_extra_docs): ?>
                        <div class="alert alert-info"><strong>ملاحظة هامة:</strong> يجب إرفاق جميع الوثائق **الستة (6)** المطلوبة لإتمام العملية بنجاح.</div>
                        <div class="form-group"><label for="doc_payment">1. وصل الدفع <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_payment" name="doc_payment" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_birth">2. شهادة الميلاد <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_birth" name="doc_birth" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_photo">3. الصورة الشمسية <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_photo" name="doc_photo" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_statement">4. التصريح الشرفي <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_statement" name="doc_statement" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_school">5. شهادة مدرسية أو شهادة التحرر من محو الأمية <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_school" name="doc_school" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_grades">6. كشف النقاط <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_grades" name="doc_grades" class="form-control" accept="application/pdf" required /></div>
                    <?php else: ?>
                        <div class="alert alert-info"><strong>ملاحظة هامة:</strong> يجب إرفاق جميع الوثائق **الأربعة (4)** المطلوبة لإتمام العملية بنجاح.</div>
                        <div class="form-group"><label for="doc_payment">1. وصل الدفع <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_payment" name="doc_payment" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_birth">2. شهادة الميلاد <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_birth" name="doc_birth" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_photo">3. الصورة الشمسية <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_photo" name="doc_photo" class="form-control" accept="application/pdf" required /></div>
                        <div class="form-group"><label for="doc_statement">4. التصريح الشرفي <span style="color:red;">(إجباري)</span></label><input type="file" id="doc_statement" name="doc_statement" class="form-control" accept="application/pdf" required /></div>
                    <?php endif; ?>
                <?php endif; ?>

                <br />
                <button type="submit" class="btn btn-success btn-lg">إرفاق الملفات المحددة</button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#uploadForm').on('submit', function(event) {
            var errorList = $('#error-list');
            var errorContainer = $('#upload-errors');
            var isValid = true;
            errorList.html(''); 
            
            $(this).find('input[type=file][required]').each(function() {
                if ($(this).get(0).files.length === 0) {
                    isValid = false;
                    var label = $("label[for='" + $(this).attr('id') + "']").text();
                    errorList.append('<li>الرجاء إرفاق ملف: ' + label + '</li>');
                }
            });

            if (!isValid) {
                event.preventDefault(); 
                errorContainer.show(); 
            } else {
                errorContainer.hide(); 
            }
        });
    });
</script>