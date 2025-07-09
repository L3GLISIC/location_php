<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Proprietaire.php';
require_once __DIR__ . '/../utils/Helper.php';

class ProprietaireController extends BaseController {

    public function handleRequest(?string $param): void {
        $id = is_numeric($param) ? (int)$param : null;

        switch ($this->requestMethod) {
            case 'GET':
                if ($id) {
                    $this->getById($id);
                } else {
                    $this->getAll();
                }
                break;
            case 'POST':
                $this->create();
                break;
            case 'DELETE':
                if ($id) {
                    $this->delete($id);
                } else {
                    $this->sendJsonResponse(['erreur' => "ID manquant pour la suppression."], 400);
                }
                break;
            default:
                $this->sendJsonResponse(['erreur' => 'M�thode non autoris�e'], 405);
                break;
        }
    }

    private function getAll() {
        $query = "
            SELECT p.*, pr.Ninea, pr.Rccm
            FROM personnes p
            JOIN proprietaires pr ON p.IdPersonne = pr.IdPersonne
            ORDER BY p.Nom, p.Prenom
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $this->sendJsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getById($id) {
        $query = "
            SELECT p.*, pr.Ninea, pr.Rccm
            FROM personnes p
            JOIN proprietaires pr ON p.IdPersonne = pr.IdPersonne
            WHERE p.IdPersonne = :id
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $proprietaire = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($proprietaire) {
            $this->sendJsonResponse($proprietaire);
        } else {
            $this->sendJsonResponse(['erreur' => 'Propri�taire non trouv�'], 404);
        }
    }

    private function create() {
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->Nom) || empty($data->Prenom) || empty($data->Telephone) || empty($data->Email) || empty($data->Ninea) || empty($data->Rccm)) {
            $this->sendJsonResponse(['erreur' => 'Donn�es incompl�tes pour la cr�ation du propri�taire.'], 400);
            return;
        }
        
        $this->db->beginTransaction();

        try {
            $stmtPersonne = $this->db->prepare("INSERT INTO personnes (Nom, Prenom, Telephone, Email) VALUES (:Nom, :Prenom, :Telephone, :Email)");
            $stmtPersonne->bindValue(':Nom', htmlspecialchars(strip_tags($data->Nom)));
            $stmtPersonne->bindValue(':Prenom', htmlspecialchars(strip_tags($data->Prenom)));
            $stmtPersonne->bindValue(':Telephone', htmlspecialchars(strip_tags($data->Telephone)));
            $stmtPersonne->bindValue(':Email', htmlspecialchars(strip_tags($data->Email)));
            $stmtPersonne->execute();
            $idPersonne = $this->db->lastInsertId();

            $stmtProprietaire = $this->db->prepare("INSERT INTO proprietaires (IdPersonne, Ninea, Rccm) VALUES (:IdPersonne, :Ninea, :Rccm)");
            $stmtProprietaire->bindValue(':IdPersonne', $idPersonne, PDO::PARAM_INT);
            $stmtProprietaire->bindValue(':Ninea', htmlspecialchars(strip_tags($data->Ninea)));
            $stmtProprietaire->bindValue(':Rccm', htmlspecialchars(strip_tags($data->Rccm)));
            $stmtProprietaire->execute();

            $this->db->commit();
            $this->sendJsonResponse(['message' => 'Propri�taire cr�� avec succ�s.', 'id' => $idPersonne], 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            Helper::WriteDataError('CreateProprietaire', $e->getMessage());
            $this->sendJsonResponse(['erreur' => "Erreur lors de la cr�ation du propri�taire: " . $e->getMessage()], 500);
        }
    }

    private function delete($id) {
        $this->db->beginTransaction();
        try {
            
            $this->db->exec("DELETE FROM proprietaires WHERE IdPersonne = $id");
            $stmt = $this->db->prepare("DELETE FROM personnes WHERE IdPersonne = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                 $this->sendJsonResponse(['erreur' => 'Propri�taire non trouv�.'], 404);
                 $this->db->rollBack();
                 return;
            }

            $this->db->commit();
            $this->sendJsonResponse(['message' => 'Propri�taire supprim� avec succ�s.']);

        } catch (Exception $e) {
            $this->db->rollBack();
            Helper::WriteDataError('DeleteProprietaire', $e->getMessage());
            $this->sendJsonResponse(['erreur' => "Erreur lors de la suppression du propri�taire. Il peut �tre li� � des appartements existants."], 500);
        }
    }

    private function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?> 