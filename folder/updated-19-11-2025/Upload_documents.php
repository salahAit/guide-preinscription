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
     * الخطوة 3: التحقق من هوية المتعلم وتطبيق جميع القواعد
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
                // 1. التحقق من شروط القبول (الدفع أو الفئات الخاصة)
                $is_paid = ($student->PAYE == 1);
                $is_worker_child = ($student->PAYE == 5); 
                $is_reeducation = ($student->FRAISINS == 1500); // استخدام FRAISINS

                if (!$is_paid && !$is_worker_child && !$is_reeducation) {
                    $this->session->set_flashdata('upload_error', 'لا يمكنك إرفاق الوثائق. يجب عليك دفع حقوق التسجيل أولاً أو أن تكون ضمن فئة معفاة.');
                    $this->_show_auth_page_with_error($code_annexe);
                    return;
                }
                
                // 2. التحقق من الإرفاق المسبق وحالة التصحيح
                $db_conn = $this->load->database('preinscription' . $code_annexe, true);
                $existing_docs = $db_conn->where('enpr', $student->ENPR)->get('student_documents')->row();
                
                $is_correction_mode = false;

                if ($existing_docs) {
                    // التحقق من حالة الملف في الإدارة (PH_ACT_AT_RLV_PY_FORM)
                    // الشرط: إذا كانت القيمة تختلف عن '00000000' و '00000001' فهذا يعني وجود خلل/تأجيل
                    $status_code = isset($student->PH_ACT_AT_RLV_PY_FORM) ? $student->PH_ACT_AT_RLV_PY_FORM : '';
                    
                    if ($status_code != '00000000' && $status_code != '00000001') {
                        $is_correction_mode = true;
                        $this->session->set_flashdata('upload_info', 'تم فتح المجال لك لإعادة رفع الوثائق لتصحيح الخطأ. سيتم استبدال الملفات القديمة.');
                    } else {
                        // المنع العادي للحالات المقبولة أو قيد المعالجة
                        $this->session->set_flashdata('upload_error', 'لقد قمت بإرفاق وثائقك مسبقًا. لا يمكنك إرفاقها مرة أخرى.');
                        $this->_show_auth_page_with_error($code_annexe);
                        return;
                    }
                }
                
                // 3. تحديد الوثائق المطلوبة
                $is_returning_student = ($student->ANNEEINS < 2026);
                $show_extra_fields = false;
                
                // الوثائق الإضافية (5 و 6) تظهر فقط للمتعلمين الجدد (غير إعادة تربية) المولودين قبل 1977
                if (!$is_returning_student && !$is_reeducation) {
                     $birth_date_obj = new DateTime($student->DNS);
                     $cutoff_date = new DateTime('1997-01-01');
                     if ($birth_date_obj < $cutoff_date) {
                         $show_extra_fields = true;
                     }
                }
                
                $session_data = [
                    'upload_student_enpr' => $student->ENPR,
                    'upload_student_name' => $student->NOM . ' ' . $student->PRENOM,
                    'upload_student_dns' => $student->DNS,
                    'upload_student_annexe' => $student->ANNEXE,
                    'is_returning_student' => $is_returning_student,
                    'is_reeducation_student' => $is_reeducation,
                    'is_worker_child' => $is_worker_child,
                    'show_extra_fields' => $show_extra_fields,
                    'is_correction_mode' => $is_correction_mode
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
     * الخطوة 4: عرض صفحة إرفاق الملفات
     */
    public function upload_form()
    {
        // منع التخزين المؤقت للصفحة لمنع العودة للخلف بعد تدمير الجلسة
        $this->output->set_header("Cache-Control: no-store, no-cache, must-revalidate, no-transform, max-age=0, post-check=0, pre-check=0");
        $this->output->set_header("Pragma: no-cache");

        if (!$this->session->userdata('upload_student_enpr')) {
            redirect('upload_documents');
        }
        
        $data['title'] = 'إرفاق الوثائق';
        $data['fich_js'] = ''; 
        $data['is_returning'] = $this->session->userdata('is_returning_student');
        $data['is_reeducation'] = $this->session->userdata('is_reeducation_student');
        $data['show_extra_fields'] = $this->session->userdata('show_extra_fields');
        
        if($this->session->userdata('is_correction_mode')) {
            $data['correction_msg'] = "أنت في وضع تصحيح الملفات. الملفات الجديدة ستعوض الملفات القديمة.";
        }

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
        $show_extra_fields = $this->session->userdata('show_extra_fields');
        
        $current_iannee = $this->config->item('annee_inscript', 'onefd_auth');
        $year_parts = explode('/', $current_iannee);
        $school_year_part = max(intval($year_parts[0]), intval($year_parts[1]));

        $uploaded_urls = [];
        $errors = '';
        $required_inputs = [];

        $all_possible_files = [
            'doc_payment'   => ['db_column' => 'doc_payment_receipt_url', 'doc_num' => '01'],
            'doc_birth'     => ['db_column' => 'doc_birth_certificate_url', 'doc_num' => '02'],
            'doc_photo'     => ['db_column' => 'doc_photo_url', 'doc_num' => '03'],
            'doc_statement' => ['db_column' => 'doc_sworn_statement_url', 'doc_num' => '04'],
            'doc_school'    => ['db_column' => 'doc_school_certificate_url', 'doc_num' => '05'],
            'doc_grades'    => ['db_column' => 'doc_transcript_url', 'doc_num' => '06']
        ];

        // تحديد الملفات الإجبارية
        if ($is_returning) {
            $required_inputs = ['doc_payment'];
        } elseif ($is_reeducation) {
            $required_inputs = ['doc_birth']; // شهادة الميلاد فقط
        } else {
            // متعلم جديد (عادي أو ابن عامل)
            $required_inputs = ['doc_payment', 'doc_birth', 'doc_photo', 'doc_statement'];
            // الوثائق الإضافية (5 و 6) تظهر في الواجهة إذا كان show_extra_fields صحيحًا لكنها اختيارية
        }
        
        $upload_base_path = './uploads/'; 
        $wilaya_path = $upload_base_path . $annexe . '/';
        if (!is_dir($wilaya_path)) {
            mkdir($wilaya_path, 0777, TRUE);
        }

        $this->load->library('upload');
        $files_processed_count = 0;
        $missing_required_files = false;

        // التحقق من وجود الملفات الإجبارية
        foreach ($required_inputs as $input_name) {
            if (empty($_FILES[$input_name]['name'])) {
                $missing_required_files = true;
                break;
            }
        }

        if (!$missing_required_files) {
            foreach ($all_possible_files as $input_name => $file_info) {
                if (!empty($_FILES[$input_name]['name'])) {
                    
                    $file_name = $annexe . $school_year_part . str_pad($enpr, 5, '0', STR_PAD_LEFT) . $file_info['doc_num'] . '.pdf';
                    $config['upload_path']    = $wilaya_path;
                    $config['allowed_types']  = 'pdf';
                    $config['max_size']       = 2048;
                    $config['file_name']      = $file_name;
                    // تفعيل الاستبدال لمنع تكرار الملفات عند إعادة الرفع
                    $config['overwrite']      = TRUE; 
                    
                    $this->upload->initialize($config);

                    if ($this->upload->do_upload($input_name)) {
                        $upload_data = $this->upload->data();
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
            'show_extra_fields' => $show_extra_fields
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
        } else if ($files_processed_count == 0 && !$is_reeducation) {
             $data['error'] = 'خطأ: لم تقم بإرفاق أي ملفات.';
             $this->load->view('menu/header', $data);
             $this->load->view('preinscription/upload/upload_form_view', $data);
             $this->load->view('menu/footer');
        } else {
            $db_conn = $this->load->database('preinscription' . $annexe, true);
            $db_data = $uploaded_urls;
            $db_data['enpr'] = $enpr;
            
            // استخدام REPLACE لتحديث السجل إذا كان موجودًا (في حالة التصحيح)
            $db_conn->replace('student_documents', $db_data);
            
            $this->_show_success_page($uploaded_urls);
        }  // ==============================================================
            // 2. التعديل الحاسم: إغلاق ملف التصحيح في INSELEVE1
            // ==============================================================
            if ($this->session->userdata('is_correction_mode')) {
                $db_conn->where('ENPR', $enpr);
                $db_conn->update('INSELEVE1', ['PH_ACT_AT_RLV_PY_FORM' => '00000000']);
                // $db_conn->update('INSELEVE11', ['PH_ACT_AT_RLV_PY_FORM' => '00000000']);
            }
    }

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
        
        $data['student_info'] = [
            'enpr' => $this->session->userdata('upload_student_enpr'),
            'name' => $this->session->userdata('upload_student_name'),
            'dns' => $this->session->userdata('upload_student_dns')
        ];
        
        $this->load->view('menu/header', $data);
        $this->load->view('preinscription/upload/upload_success_view', $data);
        $this->load->view('menu/footer');
        
        // تدمير الجلسة لمنع إعادة الرفع
        $this->session->sess_destroy();
    }
    
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
