import sys
import json
import os
from deepface import DeepFace

# Set logging level for tensorflow to suppress warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

def verify_faces(img1_path, img2_path):
    try:
        # Perform verification
        # model_name options: VGG-Face, Facenet, Facenet512, OpenFace, DeepFace, DeepID, ArcFace, Dlib, SFace
        # detector_backend options: opencv, retinaface, mtcnn, ssd, dlib, mediapipe, yolov8, centerface
        
        result = DeepFace.verify(
            img1_path = img1_path,
            img2_path = img2_path,
            model_name = "VGG-Face",
            distance_metric = "cosine",
            enforce_detection = True,
            detector_backend = "opencv",
            align = True
        )
        
        # DeepFace result is a dict with: verified, distance, threshold, model, detector_backend, similarity_metric, facial_areas, time
        output = {
            "success": True,
            "verified": result["verified"],
            "distance": result["distance"],
            "threshold": result["threshold"]
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
