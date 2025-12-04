#!/bin/bash

count=1
for f in *.jpg; do  
    new_name="_unsplash_free_$count.jpg"
    mv "$f" "$new_name"  
    
    # Resize to width 1080px and convert to JPG
    # sips -Z 1080 "$new_name"  
    # convert "$new_name" -quality 90 -sampling-factor 4:2:0 -strip "$new_name" 
    # convert "$new_name" -quality 99  "$new_name" 

    ((count++))
done
