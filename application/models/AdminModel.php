<?php
/**
 * Created by PhpStorm.
 * User: mohammadfaisalahmed
 * Date: 2/17/16
 * Time: 7:33 PM
 */

defined('BASEPATH') OR exit('No direct script access allowed');

require_once 'BaseModel.php';

class AdminModel extends BaseModel
{
    public function __construct()
    {
        parent::__construct();
    }
    function loadTransaction($userId){
        $data = array();
        $this->db->select("
            l.requested_amount requestedLoanAmount, l.approved_amount approvedLoanAmount, l.approved_date, l.distribution_date, l.installment_amount, l.cycle_due_date, l.tenor, l.installment_system,
            t.amount tranAmount, t.type, t.date, t.adjustment
        ");
        $this->db->where('l.user_id', $userId);
        $this->db->join('transaction t', 't.loan_id = l.id', 'left');
        $res = $this->db->get('loan l');

        foreach ($res->result_array() as $transaction) {
            $data[] = $transaction;
        }
        return $data;
    }

    function getApplications($status = NEW_LOAN){
        $data = array();
        $this->db->select("*");
        $this->db->from("loan");
        $this->db->join("personal_info p", "p.user_id = loan.user_id", "left");
        $this->db->join("loan_guarantor g", "g.loan_id = loan.id", "left");
        $this->db->join("users", "users.id = loan.user_id", "left");
        $this->db->where('loan.status', $status);
        $res = $this->db->get('');

        foreach ($res->result() as $row) {
            $currentApplication = array(
                'fullname' => $row->firstname . " " . $row->lastname,
                'read' => $row->read,
                'user_id' => $row->user_id,
                'student_id' => $row->student_id,
                'cgpa' => $row->student_cgpa,
                'phone' => $row->phone,
                'email' => $row->email,
                'completed_credits' => $row->completed_credits,
                'last_semester' => $row->last_semester,
                'taken_credits' => $row->taken_credits,
                'next_semester' => $row->next_semester,
                'previous_semester_gpa' => $row->previous_semester_gpa,
                'permanent_address'=> $row->permanent_address,
                'present_address' => $row->present_address,
                'note' => $row->note,
                'status' => $row->status,
                'guarantor_name' => $row->guarantor_name,
                'relation' => $row->relation,
                'guarantor_contact_no' => $row->guarantor_contact_no,
                'guarantor_address' => $row->guarantor_address,
                'requested_amount' => $row->requested_amount,
                'approved_amount' => $row->approved_amount,
                'tenor' => $row->tenor,
                'date_taken' => date("jS F, Y", $row->approved_date),
                'created_time' => date("jS F, Y", $row->created_time),
            );
            $data[] = $currentApplication;
        }

        return $data;

    }

    function getDetailsOfApplication($loan_application_user_id, $existing_loan = false){
        $data = array();

        $this->db->select('*');
        $this->db->from('loan');
        $this->db->join('personal_info p', "p.user_id = loan.user_id", "left");
        $this->db->join('users', "users.id = loan.user_id", "left");
        $this->db->join('loan_guarantor', "loan_guarantor.loan_id = loan.id", "left");
        $this->db->where("loan.user_id", $loan_application_user_id);
        $res = $this->db->get();


        foreach ($res->result_array() as $row){
            $data['details'] = $row;
            break;
        }
        $remaining_loan=0;

        if ($existing_loan) {
            $this->db->select('t.amount, t.type, t.date, t.loan_id, t.adjustment, t.id');
            $this->db->from('transaction t');
            $this->db->join('loan', "t.loan_id = loan.id", "left");
            $this->db->where("loan.user_id", $loan_application_user_id);
            $res = $this->db->get();

            foreach ($res->result_array() as $row){
                $data['transactions'][] = $row;
            }
            $transaction = $this->loadTransaction($loan_application_user_id);
            $total_return = 0;
            $total_approved_loan = $transaction[0]['approvedLoanAmount'];
            foreach($transaction as $key => $value){
                if ($value['type'] == PAYMENT_FROM_STUDENT) {
                    $total_return += $value['tranAmount'];
                    $total_return += $value['adjustment'];
                }
            }

            $remaining_loan = $total_approved_loan - $total_return;

        }
        $data['details']['remaining_loan'] = $remaining_loan;

        return $data;
    }

    function updateApplication($loan_application_user_id){
        /*
         * TODO: Email to the applicant when necessary with password
         *
         * */
        $action = $this->postGet('action');
        if ($action == 'addTransaction') {
            $data = array(
                'loan_id' => $this->postGet('loan_id'),
                'amount' => $this->postGet('amount'),
                'type' => $this->postGet('type'),
                'date' => strtotime($this->postGet('date')),
            );

            $this->db->insert('transaction', $data);
        } else if ($action == 'statusUpdate') {
            $data = array(
                'status' => $this->postGet('status'),
            );

            if ($data['status'] == EXISTING_LOAN) {
                $data['approved_date'] = time();
                $data['approved_amount'] = $this->postGet('approved_amount');
            }

            $this->db->where('user_id', $loan_application_user_id);
            $this->db->update('loan', $data);

            if ($data['status'] == EXISTING_LOAN) {
                $data = array(
                    'loan_id' => $this->postGet('loan_id'),
                    'amount' => $this->postGet('approved_amount'),
                    'type' => LOAN_TO_STUDENT,
                    'date' => time(),
                );

                $this->db->insert('transaction', $data);
            }
        }

        return true;
    }

    function writeCSV($data){
        $fileName = FCPATH . "export/data.csv";;

        $list = array(
            0 => array('Student Name', 'Student ID', 'CGPA', 'Email', 'Phone', 'Present Address', 'Permanent Address',
            'Loan Amount', 'Loan Tenor', 'Loan Reason', 'Loan Status',
            'Loan Guarantor Name', 'Loan Guarantor Relation', 'Loan Guarantor Contact No.', 'Loan Guarantor Address')
        );

        foreach ($data as $key => $value) {
            $list[] = array(
                $value['fullname'], $value['student_id'], $value['cgpa'], $value['email'], $value['phone'], $value['present_address'],$value['permanent_address'],
                $value['requested_amount'], $value['tenor'], $value['note'], $value['status'],
                $value['guarantor_name'], $value['relation'], $value['guarantor_contact_no'], $value['guarantor_address'],
            );
        }

        $fp = fopen($fileName, 'w');

        foreach ($list as $fields) {
            $status = fputcsv($fp, $fields);
            if ($status === false) {
                return false;
            }
        }

        fclose($fp);

        return true;
    }

    function updateReadStatus() {
        $userId = $this->getPost('id');
        $read = $this->getPost('changeReadStatus');

        $this->db->where('user_id', $userId);
        $this->db->update('loan', array('read' => "$read"));

        return true;
    }

    function applicationFormView($application_user_id){

        $data = array();
        $this->db->select("*");
        $this->db->from("personal_info");
        $this->db->where('personal_info.user_id', $application_user_id);
        $this->db->join("loan", "personal_info.user_id = loan.user_id", "left");
        $this->db->join("users", "users.id = personal_info.user_id", "left");
        $this->db->join("loan_guarantor g", "g.loan_id = loan.id", "left");
        $res = $this->db->get('');


        foreach ($res->result() as $row) {
            $currentApplication = array(
                'fullname' => $row->firstname . " " . $row->lastname,

                'user_id' => $row->user_id,
                'student_id' => $row->student_id,
                'cgpa' => $row->student_cgpa,
                'phone' => $row->phone,
                'email' => $row->email,
                'completed_credits' => $row->completed_credits,
                'last_semester' => $row->last_semester,
                'taken_credits' => $row->taken_credits,
                'next_semester' => $row->next_semester,
                'previous_semester_gpa' => $row->previous_semester_gpa,
                'permanent_address'=> $row->permanent_address,
                'present_address' => $row->present_address,
                'note' => $row->note,
                'status' => $row->status,
                'guarantor_name' => $row->guarantor_name,
                'relation' => $row->relation,
                'guarantor_contact_no' => $row->guarantor_contact_no,
                'guarantor_address' => $row->guarantor_address,
                'requested_amount' => $row->requested_amount,
                'approved_amount' => $row->approved_amount,
                'tenor' => $row->tenor,
                'date_taken' => date("jS F, Y", $row->approved_date),
                'created_time' => date("jS F, Y", $row->created_time),
            );
            $data = $currentApplication;
        }


        return $data;

    }
}