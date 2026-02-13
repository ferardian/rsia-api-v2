import sys
import json
import os
from deepface import DeepFace

# Set logging level for tensorflow to suppress warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

def verify_faces(img1_path, img2_path):
    try:
        # Perform verification
        # Using VGG-Face with cosine metric
        # align=True helps with accuracy for slightly tilted faces
        result = DeepFace.verify(
            img1_path = img1_path,
            img2_path = img2_path,
            model_name = "VGG-Face",
            distance_metric = "cosine",
            enforce_detection = True,
            detector_backend = "opencv", 
            align = True
        )
        
        output = {
            "success": True,
            "verified": result["verified"],
            "distance": result["distance"],
            "threshold": result["threshold"],
            "model": result.get("model", "VGG-Face"),
            "detector": result.get("detector_backend", "opencv")
        }
        
        print(json.dumps(output))
        
    except ValueError as detect_error:
        # This usually happens when "Face could not be detected"
        output = {
            "success": False,
            "error": "Wajah tidak terdeteksi dengan jelas di salah satu foto. Pastikan pencahayaan cukup dan wajah menghadap kamera."
        }
        print(json.dumps(output))
    except Exception as e:
        output = {
            "success": False,
            "error": str(e)
        }
        print(json.dumps(output))

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"success": False, "error": "Missing image paths. Usage: python3 face_verify.py img1 img2"}))
        sys.exit(1)
        
    img1 = sys.argv[1]
    img2 = sys.argv[2]
    
    if not os.path.exists(img1) or not os.path.exists(img2):
        print(json.dumps({"success": False, "error": "One or both image files do not exist."}))
        sys.exit(1)
        
    verify_faces(img1, img2)
