<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Upload_documents extends Controller_Preinscription
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['url', 'form']);
        $this->load->library(['session', 'form_validation']);
        $this->load->model('M_inscriptic_eleve');
        $this->config->load('onefd_auth', TRUE);
    }

    /**
     * الخطوة 1: عرض صفحة اختيار المركز الولائي
     */
    public function index()
    {
        $data['title'] = 'إرفاق الوثائق - اختيار المركز';
        $data['centers'] = $this->Operation_model->getAllcwefds();
        $data['fich_js'] = '';

        $this->load->view('menu/header', $data);
        $this->load->view('preinscription/upload/upload_select_wilaya_view', $data);
        $this->load->view('menu/footer');
    }

    /**
     * الخطوة 2: استقبال الولاية وعرض صفحة التحقق
     */
    public function auth()
    {
        $this->form_validation->set_rules('code_annexe', 'المركز الولائي', 'required|numeric');

        if ($this->form_validation->run() == FALSE) {
            redirect('upload_documents');
        } else {
            $data['title'] = 'إرفاق الوثائق - التحقق من الهوية';
            $data['code_annexe'] = $this->input->post('code_annexe');
            $data['captcha'] = $this->_create_captcha();
            $data['fich_js'] = '';

            $this->load->view('menu/header', $data);
            $this->load->view('preinscription/upload/upload_auth_view', $data);
            $this->load->view('menu/footer');
        }
    }

    /**
     * الخطوة 3: التحقق من هوية المتعلم وتطبيق جميع القواعد الجديدة
     */
    public function verify_student()
    {
        $this->form_validation->set_rules('enpr', 'رقم الاستمارة', 'required|numeric');
        $this->form_validation->set_rules('code_annexe', 'رمز المركز', 'required|numeric');
        $this->form_validation->set_rules('captcha', 'رمز التحقق', 'required|trim|callback_check_captcha');
        
        $is_presumed = $this->input->post('pre') == 1;

        if ($is_presumed) {
            $this->form_validation->set_rules('annee', 'سنة الميلاد', 'required|numeric|exact_length[4]');
        } else {
            $this->form_validation->set_rules('annee', 'سنة الميلاد', 'required|numeric|exact_length[4]');
            $this->form_validation->set_rules('mois', 'شهر الميلاد', 'required|numeric|exact_length[2]');
            $this->form_validation->set_rules('jour', 'يوم الميلاد', 'required|numeric|exact_length[2]');
        }

        $code_annexe = $this->input->post('code_annexe');

        if ($this->form_validation->run() == FALSE) {
            $this->_show_auth_page_with_error($code_annexe);
        } else {
            $enpr = $this->input->post('enpr');

            if ($is_presumed) {
                $dns = $this->input->post('annee') . '-01-01';
                $presume_flag = 1;
            } else {
                $dns = $this->input->post('annee') . '-' . $this->input->post('mois') . '-' . $this->input->post('jour');
                $presume_flag = 0;
            }
            
            $param = [
                'ENPR' => $enpr,
                'DNS' => $dns,
                'PRESUME' => $presume_flag,
                'CODE_ANNEXE' => $code_annexe
            ];

            $student = $this->M_inscriptic_eleve->inseleve_rechercheENBR_DNS($param);

            if ($student) {
                // =================================================================
                // تطبيق القواعد الجديدة للإدارة
                // =================================================================
                
                // القاعدة 1: التحقق من شروط القبول
                $is_paid = ($student->PAYE == 1);
                $is_worker_child = ($student->PAYE == 1500);
                $is_reeducation = ($student->FRAIS == 1500);

                if (!$is_paid && !$is_worker_child && !$is_reeducation) {
                    $this->session->set_flashdata('upload_error', 'لا يمكنك إرفاق الوثائق. يجب عليك دفع حقوق التسجيل أولاً أو أن تكون ضمن فئة معفاة.');
                    $this->_show_auth_page_with_error($code_annexe);
                    return;
                }
                
                $db_conn = $this->load->database('preinscription' . $code_annexe, true);
                $existing_docs = $db_conn->where('enpr', $student->ENPR)->get('student_documents')->row();

                if ($existing_docs) {
                    $this->session->set_flashdata('upload_error', 'لقد قمت بإرفاق وثائقك مسبقًا. لا يمكنك إرفاقها مرة أخرى.');
                    $this->_show_auth_page_with_error($code_annexe);
                    return;
                }
                
                // القاعدة 2: تحديد الوثائق المطلوبة
                $is_returning_student = ($student->ANNEEINS < 2026);
                $requires_extra_docs = false;
                
                // الوثائق الإضافية مطلوبة فقط للمتعلمين الجدد (العاديين أو أبناء العمال)
                if (!$is_returning_student && ($is_paid || $is_worker_child)) {
                    $requires_extra_docs = true; // القاعدة الجديدة: الكل يرفق 6 وثائق
                }
                // =================================================================
                
                $session_data = [
                    'upload_student_enpr' => $student->ENPR,
                    'upload_student_name' => $student->NOM . ' ' . $student->PRENOM,
                    'upload_student_dns' => $student->DNS, // جديد: لإضافته للوصل
                    'upload_student_annexe' => $student->ANNEXE,
                    'upload_student_iannee' => $student->IANNEE,
                    'is_returning_student' => $is_returning_student,
                    'is_reeducation_student' => $is_reeducation, // جديد
                    'is_worker_child' => $is_worker_child, // جديد
                    'requires_extra_docs' => $requires_extra_docs
                ];
                $this->session->set_userdata($session_data);
                redirect('upload_documents/upload_form');

            } else {
                $this->session->set_flashdata('upload_error', 'المعلومات المدخلة غير صحيحة. لا يوجد متعلم مسجل بهذه البيانات.');
                $this->_show_auth_page_with_error($code_annexe);
            }
        }
    }
    
    /**
     * الخطوة 4: عرض صفحة إرفاق الملفات الديناميكية
     */
    public function upload_form()
    {
        if (!$this->session->userdata('upload_student_enpr')) {
            redirect('upload_documents');
        }
        $data['title'] = 'إرفاق الوثائق';
        $data['fich_js'] = ''; 
        $data['is_returning'] = $this->session->userdata('is_returning_student');
        $data['is_reeducation'] = $this->session->userdata('is_reeducation_student');
        $data['requires_extra_docs'] = $this->session->userdata('requires_extra_docs');

        $this->load->view('menu/header', $data);
        $this->load->view('preinscription/upload/upload_form_view', $data);
        $this->load->view('menu/footer');
    }

    /**
     * الخطوة 5: معالجة رفع الملفات
     */
    public function do_upload()
    {
        if (!$this->session->userdata('upload_student_enpr')) { redirect('upload_documents'); }

        $enpr = $this->session->userdata('upload_student_enpr');
        $annexe = $this->session->userdata('upload_student_annexe');
        $is_returning = $this->session->userdata('is_returning_student');
        $is_reeducation = $this->session->userdata('is_reeducation_student');
        $requires_extra = $this->session->userdata('requires_extra_docs');
        
        $current_iannee = $this->config->item('annee_inscript', 'onefd_auth');
        $year_parts = explode('/', $current_iannee);
        $school_year_part = max(intval($year_parts[0]), intval($year_parts[1]));

        $uploaded_urls = [];
        $errors = '';
        $required_inputs = [];

        // قائمة بكل الملفات المحتملة
        $all_possible_files = [
            'doc_payment'   => ['db_column' => 'doc_payment_receipt_url', 'doc_num' => '01'],
            'doc_birth'     => ['db_column' => 'doc_birth_certificate_url', 'doc_num' => '02'],
            'doc_photo'     => ['db_column' => 'doc_photo_url', 'doc_num' => '03'],
            'doc_statement' => ['db_column' => 'doc_sworn_statement_url', 'doc_num' => '04'],
            'doc_school'    => ['db_column' => 'doc_school_certificate_url', 'doc_num' => '05'],
            'doc_grades'    => ['db_column' => 'doc_transcript_url', 'doc_num' => '06']
        ];

        // تحديد الملفات الإجبارية بناءً على القواعد الجديدة
        if ($is_returning) {
            $required_inputs = ['doc_payment'];
        } elseif ($is_reeducation) {
            $required_inputs = ['doc_birth']; // شهادة الميلاد فقط
        } else {
            // متعلم جديد (عادي أو ابن عامل)
            $required_inputs = ['doc_payment', 'doc_birth', 'doc_photo', 'doc_statement'];
            if ($requires_extra) {
                $required_inputs = array_merge($required_inputs, ['doc_school', 'doc_grades']);
            }
        }
        
        // المسار الحالي (قبل التغيير الأمني)
        $upload_base_path = './uploads/'; 
        $wilaya_path = $upload_base_path . $annexe . '/';
        if (!is_dir($wilaya_path)) {
            mkdir($wilaya_path, 0777, TRUE);
        }

        $this->load->library('upload');
        $files_processed_count = 0;
        $missing_required_files = false;

        // التحقق من أن جميع الملفات الإجبارية موجودة
        foreach ($required_inputs as $input_name) {
            if (empty($_FILES[$input_name]['name'])) {
                $missing_required_files = true;
                break;
            }
        }

        // معالجة فقط الملفات التي تم إرسالها
        if (!$missing_required_files) {
            foreach ($all_possible_files as $input_name => $file_info) {
                if (!empty($_FILES[$input_name]['name'])) {
                    
                    $file_name = $annexe . $school_year_part . str_pad($enpr, 5, '0', STR_PAD_LEFT) . $file_info['doc_num'] . '.pdf';
                    $config['upload_path']    = $wilaya_path;
                    $config['allowed_types']  = 'pdf';
                    $config['max_size']       = 2048;
                    $config['file_name']      = $file_name;
                    
                    $this->upload->initialize($config);

                    if ($this->upload->do_upload($input_name)) {
                        $upload_data = $this->upload->data();
                        // حفظ الرابط الكامل (قبل التغيير الأمني)
                        $uploaded_urls[$file_info['db_column']] = base_url(str_replace('./', '', $wilaya_path) . $upload_data['file_name']);
                        $files_processed_count++;
                    } else {
                        $errors .= '<p>خطأ في رفع ملف (' . $input_name . '): ' . $this->upload->display_errors('', '') . '</p>';
                    }
                }
            }
        }
        
        $data = [
            'title' => 'إرفاق الوثائق',
            'fich_js' => '',
            'is_returning' => $is_returning,
            'is_reeducation' => $is_reeducation,
            'requires_extra_docs' => $requires_extra
        ];

        if (!empty($errors)) {
            $data['error'] = $errors;
            $this->load->view('menu/header', $data);
            $this->load->view('preinscription/upload/upload_form_view', $data);
            $this->load->view('menu/footer');
        } else if ($missing_required_files) {
             $data['error'] = 'خطأ: يجب إرفاق جميع الوثائق الإجبارية المحددة لحالتك.';
             $this->load->view('menu/header', $data);
             $this->load->view('preinscription/upload/upload_form_view', $data);
             $this->load->view('menu/footer');
        } else if ($files_processed_count == 0) {
             $data['error'] = 'خطأ: لم تقم بإرفاق أي ملفات.';
             $this->load->view('menu/header', $data);
             $this->load->view('preinscription/upload/upload_form_view', $data);
             $this->load->view('menu/footer');
        } else {
            $db_conn = $this->load->database('preinscription' . $annexe, true);
            $db_data = $uploaded_urls;
            $db_data['enpr'] = $enpr;
            
            $db_conn->replace('student_documents', $db_data);
            
            $this->_show_success_page($uploaded_urls);
        }
    }

    /**
     * الخطوة 6: عرض صفحة النجاح مع بيانات الوصل
     */
    private function _show_success_page($uploaded_urls)
    {
        $doc_labels = [
            'doc_payment_receipt_url' => 'وصل الدفع',
            'doc_birth_certificate_url' => 'شهادة الميلاد',
            'doc_photo_url' => 'الصورة الشمسية',
            'doc_sworn_statement_url' => 'التصريح الشرفي',
            'doc_school_certificate_url' => 'شهادة مدرسية أو شهادة التحرر من محو الأمية',
            'doc_transcript_url' => 'كشف النقاط'
        ];

        $display_files = [];
        foreach($uploaded_urls as $db_column => $url) {
            if (isset($doc_labels[$db_column])) {
                $display_files[$doc_labels[$db_column]] = $url;
            }
        }
        $data['uploaded_files_display'] = $display_files;
        $data['title'] = 'نجاح الإرفاق';
        $data['fich_js'] = '';
        
        // التعديل 3: إضافة بيانات المتعلم للوصل
        $data['student_info'] = [
            'enpr' => $this->session->userdata('upload_student_enpr'),
            'name' => $this->session->userdata('upload_student_name'),
            'dns' => $this->session->userdata('upload_student_dns')
        ];
        
        $this->load->view('menu/header', $data);
        $this->load->view('preinscription/upload/upload_success_view', $data);
        $this->load->view('menu/footer');
        
        $this->session->sess_destroy(); // تدمير الجلسة بالكامل بعد النجاح
    }
    
    /**
     * دالة مساعدة لعرض صفحة التحقق مع الأخطاء
     */
    private function _show_auth_page_with_error($code_annexe)
    {
        $data['title'] = 'إرفاق الوثائق - التحقق من الهوية';
        $data['code_annexe'] = $code_annexe;
        $data['captcha'] = $this->_create_captcha();
        $data['fich_js'] = '';
        $this->session->keep_flashdata('upload_error');
        
        $this->load->view('menu/header', $data);
        $this->load->view('preinscription/upload/upload_auth_view', $data);
        $this->load->view('menu/footer');
    }
}