#!/usr/bin/env python3
"""
Face detection for event photos using OpenCV Haar cascades.
Returns JSON: {"faces": N, "has_faces": bool}
Called by PHP's FaceDetectionService.

Usage: python3 detect_faces.py /path/to/image.jpg

@author ClubCEP.eu
"""

import sys
import json
import cv2

CASCADE_DIR = "/home/collaed/laravel/divingclub/storage/app/ml"

def detect_faces(image_path):
    img = cv2.imread(image_path)
    if img is None:
        return {"faces": 0, "has_faces": False, "error": "Cannot read image"}

    # Downscale for speed (max 800px wide)
    h, w = img.shape[:2]
    if w > 800:
        scale = 800 / w
        img = cv2.resize(img, (800, int(h * scale)))

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    gray = cv2.equalizeHist(gray)  # improve detection in varied lighting

    # Frontal face detection
    frontal = cv2.CascadeClassifier(f"{CASCADE_DIR}/haarcascade_frontalface_default.xml")
    faces_frontal = frontal.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(30, 30))

    # Profile face detection (catches side views)
    profile = cv2.CascadeClassifier(f"{CASCADE_DIR}/haarcascade_profileface.xml")
    faces_profile = profile.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(30, 30))

    total = len(faces_frontal) + len(faces_profile)
    return {"faces": total, "has_faces": total > 0}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: detect_faces.py <image_path>"}))
        sys.exit(1)

    result = detect_faces(sys.argv[1])
    print(json.dumps(result))
