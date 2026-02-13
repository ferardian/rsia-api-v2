import os
import sys
import json

# Avoid permission errors and disk full by redirecting home to a writable path with more space
os.environ['DEEPFACE_HOME'] = '/home/sysadmin/ai_env_presensi/.deepface'

# Fix for SymbolAlreadyExposedError in some TF/Keras versions
os.environ['TF_USE_LEGACY_KERAS'] = '1'
# Set logging level for tensorflow to suppress warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

from deepface import DeepFace

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
    
    # Debug info for paths
    if not os.path.exists(img1):
        print(json.dumps({"success": False, "error": f"Master image not found at path: {img1}"}))
        sys.exit(1)
    if not os.path.exists(img2):
        print(json.dumps({"success": False, "error": f"Submitted image not found at path: {img2}"}))
        sys.exit(1)
        
    verify_faces(img1, img2)
